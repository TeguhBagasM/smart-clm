<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\ReceiptStatus;
use App\Models\Dcs\Distribution;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ReadReceipt;
use App\Models\User;
use App\Support\Dcs\DcsException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sends a version to a set of users of the tenant: one distribution and a
 * pending read receipt for each. Repeating it never resets a user who already
 * read or acknowledged; it only points their receipt at the newest distribution.
 */
class DistributeVersion
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent) {}

    /**
     * @param  list<string>  $legacyUserIds  the user ids the React client knows (Node ids)
     *
     * @throws DcsException
     */
    public function handle(User $actor, DocumentVersion $version, array $legacyUserIds, bool $mandatory): Distribution
    {
        $legacyUserIds = array_values(array_unique($legacyUserIds));
        $users = User::query()->where('tenant_id', $version->tenant_id)->whereIn('legacy_id', $legacyUserIds)->get();
        $unknown = array_diff($legacyUserIds, $users->pluck('legacy_id')->all());

        if ($unknown !== []) {
            throw DcsException::badRequest('Penerima tidak ditemukan: '.implode(', ', $unknown).'.');
        }

        return $this->toUsers($actor, $version, $users, $mandatory);
    }

    /**
     * Sends the version to users of its tenant that are already loaded.
     *
     * @param  Collection<int, User>  $users
     */
    public function toUsers(User $actor, DocumentVersion $version, Collection $users, bool $mandatory): Distribution
    {
        return DB::transaction(function () use ($actor, $version, $users, $mandatory): Distribution {
            $distribution = new Distribution;
            $distribution->forceFill([
                'tenant_id' => $version->tenant_id,
                'version_id' => $version->id,
                'recipient_count' => $users->count(),
                'is_mandatory' => $mandatory,
                'distributed_by_id' => $actor->id,
            ])->save();

            foreach ($users as $user) {
                $receipt = ReadReceipt::query()->where('version_id', $version->id)->where('user_id', $user->id)->first() ?? new ReadReceipt;
                $receipt->forceFill(['tenant_id' => $version->tenant_id, 'version_id' => $version->id, 'user_id' => $user->id, 'distribution_id' => $distribution->id]);

                if (! $receipt->exists) {
                    $receipt->status = ReceiptStatus::Pending;
                }

                $receipt->save();
            }

            $this->recordDocumentEvent->handle($version->document, DocumentEventType::Distributed, $actor, $version, ['distribution' => $distribution->uuid, 'recipients' => $users->count(), 'mandatory' => $mandatory], reviewRound: $version->review_round);

            return $distribution;
        });
    }
}
