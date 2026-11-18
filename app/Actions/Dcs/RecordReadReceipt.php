<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\ReceiptStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ReadReceipt;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Records that a user opened (or explicitly acknowledged) a version. The receipt
 * only moves forward: pending → read → acknowledged, and first-read and
 * acknowledgement times are never overwritten. Anyone who opens a version gets a
 * receipt, distributed to them or not.
 */
class RecordReadReceipt
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent) {}

    public function handle(User $user, DocumentVersion $version, ?string $ip, ?string $userAgent, bool $acknowledge = false): void
    {
        try {
            $this->write($user, $version, $ip, $userAgent, $acknowledge);
        } catch (UniqueConstraintViolationException) {
            $this->write($user, $version, $ip, $userAgent, $acknowledge);
        }
    }

    private function write(User $user, DocumentVersion $version, ?string $ip, ?string $userAgent, bool $acknowledge): void
    {
        DB::transaction(function () use ($user, $version, $ip, $userAgent, $acknowledge): void {
            $receipt = ReadReceipt::query()->where('version_id', $version->id)->where('user_id', $user->id)->lockForUpdate()->first() ?? new ReadReceipt;
            $firstAcknowledgement = $acknowledge && $receipt->acknowledged_at === null;

            $receipt->forceFill([
                'tenant_id' => $version->tenant_id,
                'version_id' => $version->id,
                'user_id' => $user->id,
                'status' => $acknowledge || $receipt->status === ReceiptStatus::Acknowledged ? ReceiptStatus::Acknowledged : ReceiptStatus::Read,
                'read_at' => $receipt->read_at ?? now(),
                'acknowledged_at' => $receipt->acknowledged_at ?? ($acknowledge ? now() : null),
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ])->save();

            if ($firstAcknowledgement) {
                $this->recordDocumentEvent->handle($version->document, DocumentEventType::Acknowledged, $user, $version, reviewRound: $version->review_round);
            }
        });
    }
}
