<?php

use App\Actions\Dcs\ActivateDueReleases;
use App\Actions\Dcs\CancelVersionRelease;
use App\Actions\Dcs\DistributeVersion;
use App\Actions\Dcs\RecordReadReceipt;
use App\Actions\Dcs\ReleaseVersion;
use App\Actions\Dcs\ScheduleVersionRelease;
use App\Actions\Dcs\SendReviewReminders;
use App\Actions\Dcs\StoreRendition;
use App\Actions\Dcs\ViewVersion;
use App\Actions\Dcs\WithdrawVersion;
use App\Enums\Dcs\ActorType;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\ReceiptStatus;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ReadReceipt;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Notifications\DcsNotifier;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakePdfEngine;
use Tests\Support\RecordingNotifier;

/*
 * The rules of releasing, reminding, viewing and distributing, held at the Action and the
 * commands (these used to be checked only through the legacy Node-style API).
 */

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    $this->engine = new FakePdfEngine;
    $this->notifier = new RecordingNotifier;
    app()->instance(PdfEngine::class, $this->engine);
    app()->instance(DcsNotifier::class, $this->notifier);
});

/**
 * @return array{admin: User, document: Document, version: DocumentVersion}
 */
function relFixture(VersionStatus $status = VersionStatus::Approved, ?Tenant $tenant = null, ?Document $document = null, int $major = 1): array
{
    $tenant ??= Tenant::factory()->create();
    $admin = $document ? $document->owner : User::factory()->for($tenant)->role(SystemRole::Admin)->create();
    $document ??= Document::factory()->for($tenant)->create(['owner_user_id' => $admin->id, 'created_by_id' => $admin->id]);
    $version = DocumentVersion::factory()->for($document)->create(['status' => $status, 'major' => $major, 'created_by_id' => $admin->id]);
    app(StoreRendition::class)->handle($admin, $version, RenditionKind::Composed, FakePdfEngine::pdf());

    return compact('admin', 'document', 'version');
}

function viewAs(User $user, DocumentVersion $version, bool $english = false): array
{
    return app(ViewVersion::class)->handle($user, $version->fresh(), $english, '127.0.0.1', 'pest');
}

describe('releasing', function () {
    it('brings an approved version into force and supersedes the one in force at the same instant', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $old] = relFixture();
        app(ReleaseVersion::class)->handle($admin, $document, $old);
        ['version' => $new] = relFixture(document: $document, major: 2);

        $result = app(ReleaseVersion::class)->handle($admin, $document, $new);

        $old->refresh();
        $new->refresh();
        expect($result['supersededVersion']->id)->toBe($old->id)->and($new->status)->toBe(VersionStatus::Effective)->and($old->status)->toBe(VersionStatus::Superseded)
            ->and($old->superseded_at->equalTo($new->effective_at))->toBeTrue()->and($document->fresh()->current_version_id)->toBe($new->id);
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::Released)->count())->toBe(2)
            ->and(DocumentEvent::query()->where('event_type', DocumentEventType::Superseded)->sole()->version_id)->toBe($old->id);
    });

    it('uses an explicit date that is not in the future as the effective date', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $version] = relFixture();

        app(ReleaseVersion::class)->handle($admin, $document, $version, now()->parse('2026-01-05T00:00:00Z'));

        expect($version->fresh()->effective_at->toDateString())->toBe('2026-01-05');
    });

    it('releases due versions at their scheduled moment and leaves the rest', function () {
        ['admin' => $admin, 'version' => $due] = relFixture();
        $due->forceFill(['scheduled_effective_at' => now()->subHours(3)])->save();
        ['version' => $later] = relFixture(tenant: $admin->tenant);
        $later->forceFill(['scheduled_effective_at' => now()->addDay()])->save();
        ['version' => $manual] = relFixture(tenant: $admin->tenant);
        $scheduledFor = $due->scheduled_effective_at;

        expect(app(ActivateDueReleases::class)->handle())->toBe(1);

        $due->refresh();
        expect($due->status)->toBe(VersionStatus::Effective)->and($due->effective_at->equalTo($scheduledFor))->toBeTrue()->and($due->scheduled_effective_at)->toBeNull()
            ->and($later->fresh()->status)->toBe(VersionStatus::Approved)->and($manual->fresh()->status)->toBe(VersionStatus::Approved);
        $event = DocumentEvent::query()->where('event_type', DocumentEventType::Released)->sole();
        expect($event->actor_type)->toBe(ActorType::System)->and($this->notifier->sent[0]['title'])->toBe('Dokumen Berlaku Otomatis (Terjadwal)');
    });

    it('is run by the dcs:release-due command', function () {
        ['version' => $due] = relFixture();
        $due->forceFill(['scheduled_effective_at' => now()->subMinute()])->save();

        $this->artisan('dcs:release-due')->assertSuccessful();

        expect($due->fresh()->status)->toBe(VersionStatus::Effective);
    });
});

describe('withdrawing', function () {
    it('retires the version in force without a replacement', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $version] = relFixture();
        app(ReleaseVersion::class)->handle($admin, $document, $version);

        app(WithdrawVersion::class)->handle($admin, $document->fresh(), $version->fresh());

        expect($version->fresh()->status)->toBe(VersionStatus::Superseded)->and($version->fresh()->superseded_at)->not->toBeNull()->and($document->fresh()->current_version_id)->toBeNull()
            ->and(DocumentEvent::query()->where('event_type', DocumentEventType::Withdrawn)->count())->toBe(1);
    });

    it('refuses a version that is not in force, with the reason', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $version] = relFixture();

        expect(fn () => app(WithdrawVersion::class)->handle($admin, $document, $version))
            ->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(409)->and($e->getMessage())->toBe('Hanya dokumen berstatus Effective yang bisa dinonaktifkan.'));
        expect($version->fresh()->status)->toBe(VersionStatus::Approved);
    });
});

describe('review reminders', function () {
    it('sends each reminder once a day, inside the window only', function () {
        $this->travelTo('2026-07-05 10:00:00');
        ['admin' => $admin, 'version' => $soon] = relFixture(VersionStatus::Effective);
        $soon->forceFill(['review_due_at' => '2026-07-20 00:00:00', 'review_reminder_days' => 30])->save();
        ['version' => $far] = relFixture(VersionStatus::Effective, $admin->tenant);
        $far->forceFill(['review_due_at' => '2026-12-01 00:00:00', 'review_reminder_days' => 30])->save();
        ['version' => $overdue] = relFixture(VersionStatus::Effective, $admin->tenant);
        $overdue->forceFill(['review_due_at' => '2026-07-01 00:00:00'])->save();
        ['version' => $draft] = relFixture(VersionStatus::Draft, $admin->tenant);
        $draft->forceFill(['review_due_at' => '2026-07-06 00:00:00'])->save();

        app(SendReviewReminders::class)->handle();
        app(SendReviewReminders::class)->handle();

        expect($this->notifier->sent)->toHaveCount(2);
        $byType = collect($this->notifier->sent)->keyBy('type');
        expect($byType['info']['message'])->toContain('dalam 15 hari')->and($byType['danger']['message'])->toContain('4 hari yang lalu')
            ->and($byType['danger']['title'])->toBe('Dokumen Lewat Jadwal Tinjau Ulang')->and($byType['info']['recipients'])->toContain($admin->id);

        $this->travelTo('2026-07-06 10:00:00');
        app(SendReviewReminders::class)->handle();

        expect($this->notifier->sent)->toHaveCount(4);
    });
});

describe('viewing', function () {
    it('marks an effective version past its review date as expired', function () {
        ['admin' => $admin, 'version' => $version] = relFixture(VersionStatus::Effective);
        $version->forceFill(['review_due_at' => now()->subDay()])->save();

        viewAs($admin, $version);

        expect($this->engine->watermarked[0]['expired'])->toBeTrue();
    });

    it('serves the English version only when a translation exists', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $version] = relFixture(VersionStatus::Effective);

        expect(fn () => viewAs($admin, $version, true))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(404)->and($e->getMessage())->toBe('Versi bahasa Inggris belum tersedia untuk dokumen ini.'));

        app(StoreRendition::class)->handle($admin, $version, RenditionKind::Translated, FakePdfEngine::pdf('english'), language: 'en');

        expect(viewAs($admin, $version, true)['filename'])->toBe(str_replace('/', '-', $document->document_number).'-EN');
    });

    it('refuses a tampered file, a missing file and an engine failure without leaving a receipt', function () {
        ['admin' => $admin, 'version' => $version] = relFixture(VersionStatus::Effective);
        Storage::disk('dcs')->put($version->currentRendition()->storage_key, FakePdfEngine::pdf('tampered'));

        expect(fn () => viewAs($admin, $version))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(409)->and($e->getMessage())->toBe('Integritas dokumen gagal diverifikasi — akses diblokir. Hubungi Document Control.'));

        ['version' => $empty] = relFixture(VersionStatus::Draft, $admin->tenant);
        $empty->renditions()->delete();
        expect(fn () => viewAs($admin, $empty))->toThrow(DcsException::class, 'Versi ini belum memiliki berkas PDF');

        ['version' => $ok] = relFixture(VersionStatus::Effective, $admin->tenant);
        $this->engine->fail = true;
        expect(fn () => viewAs($admin, $ok))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(500)->and($e->getMessage())->toBe('Gagal menampilkan dokumen.'));
        expect(ReadReceipt::query()->count())->toBe(0);
    });
});

describe('distribution', function () {
    it('sends a version to users and never resets someone who already read it', function () {
        ['admin' => $admin, 'version' => $version] = relFixture(VersionStatus::Effective);
        $rina = User::factory()->for($admin->tenant)->role(SystemRole::Viewer)->create();
        $budi = User::factory()->for($admin->tenant)->role(SystemRole::Viewer)->create();
        $distribute = fn (array $users) => app(DistributeVersion::class)->toUsers($admin, $version, collect($users), true);

        $distribute([$rina, $budi, $rina]);
        expect(ReadReceipt::query()->pluck('status')->unique()->all())->toBe([ReceiptStatus::Pending]);

        viewAs($rina, $version);
        $distribute([$rina]);

        expect(ReadReceipt::query()->where('user_id', $rina->id)->sole()->status)->toBe(ReceiptStatus::Read)->and(ReadReceipt::query()->where('user_id', $budi->id)->sole()->status)->toBe(ReceiptStatus::Pending)
            ->and(DocumentEvent::query()->where('event_type', DocumentEventType::Distributed)->count())->toBe(2);
    });

    it('records an acknowledgement once and keeps its first time', function () {
        ['admin' => $admin, 'version' => $version] = relFixture(VersionStatus::Effective);
        $rina = User::factory()->for($admin->tenant)->role(SystemRole::Viewer)->create();
        app(DistributeVersion::class)->toUsers($admin, $version, collect([$rina]), true);

        app(RecordReadReceipt::class)->handle($rina, $version, null, null, true);
        $first = ReadReceipt::query()->sole()->acknowledged_at;
        $this->travel(5)->minutes();
        app(RecordReadReceipt::class)->handle($rina, $version, null, null, true);
        viewAs($rina, $version);

        $receipt = ReadReceipt::query()->sole();
        expect($receipt->status)->toBe(ReceiptStatus::Acknowledged)->and($receipt->acknowledged_at->equalTo($first))->toBeTrue()
            ->and(DocumentEvent::query()->where('event_type', DocumentEventType::Acknowledged)->count())->toBe(1);
    });
});

describe('scheduling', function () {
    it('keeps a version approved until its date, tells the releaser, and cancels the schedule once', function () {
        ['admin' => $admin, 'version' => $version] = relFixture();
        $when = now()->addDays(3)->startOfSecond();

        app(ScheduleVersionRelease::class)->handle($admin, $version, $when);

        $version->refresh();
        expect($version->status)->toBe(VersionStatus::Approved)->and($version->scheduled_effective_at->equalTo($when))->toBeTrue()
            ->and(DocumentEvent::query()->where('event_type', DocumentEventType::ReleaseScheduled)->count())->toBe(1)->and($this->notifier->sent[0]['recipients'])->toBe([$admin->id]);

        app(CancelVersionRelease::class)->handle($admin, $version->fresh());
        app(CancelVersionRelease::class)->handle($admin, $version->fresh());

        expect($version->fresh()->scheduled_effective_at)->toBeNull()->and(DocumentEvent::query()->where('event_type', DocumentEventType::ReleaseScheduleCancelled)->count())->toBe(1);
    });

    it('refuses versions that are not approved, with the reason', function (VersionStatus $status) {
        ['admin' => $admin, 'document' => $document, 'version' => $version] = relFixture($status);

        expect(fn () => app(ReleaseVersion::class)->handle($admin, $document, $version))->toThrow(DcsException::class, "Illegal DCS state transition: {$status->value} → effective");
        expect(fn () => app(ScheduleVersionRelease::class)->handle($admin, $version, now()->addWeek()))->toThrow(DcsException::class, 'Hanya dokumen yang sudah disetujui penuh (status Approved) yang bisa dijadwalkan berlaku.');
        expect($version->fresh()->status)->toBe($status);
    })->with([VersionStatus::Draft, VersionStatus::UnderReview, VersionStatus::Effective, VersionStatus::Superseded]);
});

describe('what a reader gets', function () {
    it('serves a copy stamped with the live status and the reader, and leaves a read receipt', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $version] = relFixture();
        app(ReleaseVersion::class)->handle($admin, $document, $version);
        $reader = User::factory()->for($admin->tenant)->role(SystemRole::Viewer)->create();

        $copy = viewAs($reader, $version);

        expect($copy['bytes'])->toStartWith('%PDF')->and($copy['status'])->toBe('effective')->and($copy['filename'])->toBe(str_replace('/', '-', $document->document_number));
        expect($this->engine->watermarked[0])->toMatchArray(['status' => 'effective', 'viewerName' => $reader->name, 'documentNumber' => $document->document_number, 'major' => 1, 'minor' => 0, 'expired' => false])
            ->and($this->engine->watermarked[0]['accessedAt'])->toEndWith(' UTC')->and($this->engine->watermarked[0]['issuedDateIso'])->not->toBeNull();
        expect(ReadReceipt::query()->where('user_id', $reader->id)->sole()->status)->toBe(ReceiptStatus::Read);
    });

    it('refuses recipients that are not users of the company', function () {
        ['admin' => $admin, 'version' => $version] = relFixture(VersionStatus::Effective);
        User::factory()->for(Tenant::factory()->create())->create(['legacy_id' => 'usr-foreign']);

        expect(fn () => app(DistributeVersion::class)->handle($admin, $version, ['usr-foreign'], true))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(400));
        expect(ReadReceipt::query()->count())->toBe(0);
    });
});
