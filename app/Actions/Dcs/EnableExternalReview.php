<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ExternalReview;
use App\Models\User;
use App\Support\Dcs\ApiDate;
use App\Support\Dcs\DcsException;
use Illuminate\Support\Facades\DB;

/**
 * Opens a review link for outside parties on a draft or a version under
 * review. A link already open for the version is replaced (its token stops
 * working). The plain token is returned once; only its hash is stored.
 */
class EnableExternalReview
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent) {}

    /**
     * @return array{token: string, expiresAt: string|null}
     *
     * @throws DcsException
     */
    public function handle(User $actor, DocumentVersion $version, ?int $expiresInDays): array
    {
        $token = bin2hex(random_bytes(24));
        $expiresAt = $expiresInDays !== null && $expiresInDays > 0 ? now()->addDays($expiresInDays) : null;

        DB::transaction(function () use ($actor, $version, $token, $expiresAt): void {
            $locked = DocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [VersionStatus::Draft, VersionStatus::UnderReview], true)) {
                throw DcsException::conflict('Versi tidak ditemukan, atau statusnya tidak mendukung review eksternal (hanya Draft/Under Review).');
            }

            ExternalReview::query()->where('version_id', $locked->id)->whereNull('disabled_at')->update(['disabled_at' => now()]);

            $review = new ExternalReview;
            $review->forceFill([
                'tenant_id' => $locked->tenant_id, 'version_id' => $locked->id, 'token_hash' => ExternalReview::hashToken($token),
                'expires_at' => $expiresAt, 'locked' => false, 'created_by_id' => $actor->id,
            ])->save();

            $this->recordDocumentEvent->handle($locked->document, DocumentEventType::ExternalReviewEnabled, $actor, $locked, ['expires_at' => ApiDate::format($expiresAt)], reviewRound: $locked->review_round);
        });

        return ['token' => $token, 'expiresAt' => ApiDate::format($expiresAt)];
    }
}
