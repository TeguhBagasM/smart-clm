<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Notifications\DcsNotifier;
use Throwable;

/**
 * The scheduler's half of a scheduled release: every approved version whose
 * date has arrived comes into force, dated at its scheduled moment rather than
 * when this ran. One failing version is reported and retried on the next run.
 */
class ActivateDueReleases
{
    public function __construct(private ReleaseVersion $releaseVersion, private DcsNotifier $notifier) {}

    /**
     * @return int how many versions were released
     */
    public function handle(): int
    {
        $released = 0;

        DocumentVersion::query()
            ->where('status', VersionStatus::Approved->value)
            ->whereNotNull('scheduled_effective_at')
            ->where('scheduled_effective_at', '<=', now())
            ->with('document')
            ->lazyById()
            ->each(function (DocumentVersion $version) use (&$released): void {
                try {
                    $this->releaseVersion->handle(null, $version->document, $version, $version->scheduled_effective_at);
                } catch (DcsException $e) {
                    report($e);

                    return;
                } catch (Throwable $e) {
                    report($e);

                    return;
                }

                $released++;
                $document = $version->document;

                $this->notifier->notify(
                    User::query()->whereKey($document->owner_user_id)->get(),
                    $document,
                    'Dokumen Berlaku Otomatis (Terjadwal)',
                    "{$document->document_number} ({$document->title}) kini resmi berlaku sesuai jadwal rilis.",
                    'success',
                );
            });

        return $released;
    }
}
