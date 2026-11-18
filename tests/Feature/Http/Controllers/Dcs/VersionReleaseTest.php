<?php

use App\Actions\Dcs\StoreRendition;
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
use App\Support\Dcs\Notifications\DcsNotifier;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakePdfEngine;
use Tests\Support\RecordingNotifier;

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    $this->engine = new FakePdfEngine;
    $this->notifier = new RecordingNotifier;
    app()->instance(PdfEngine::class, $this->engine);
    app()->instance(DcsNotifier::class, $this->notifier);
});

/**
 * A tenant with an admin (owner of the document and holder of dcs.release) and
 * a document whose first version has the given status and a stored PDF.
 *
 * @return array{admin: User, document: Document, version: DocumentVersion}
 */
function releaseFixture(VersionStatus $status = VersionStatus::Approved, ?Tenant $tenant = null, ?Document $document = null, int $major = 1): array
{
    $tenant ??= Tenant::factory()->create();
    $admin = $document ? $document->owner : User::factory()->for($tenant)->role(SystemRole::Admin)->create();
    $document ??= Document::factory()->for($tenant)->create(['owner_user_id' => $admin->id, 'created_by_id' => $admin->id]);
    $version = DocumentVersion::factory()->for($document)->create(['status' => $status, 'major' => $major, 'created_by_id' => $admin->id]);
    app(StoreRendition::class)->handle($admin, $version, RenditionKind::Composed, FakePdfEngine::pdf());

    return compact('admin', 'document', 'version');
}

function call(User $user, string $method, string $path, array $payload = [])
{
    return dcsAs($user)->{$method}("/api/dcs/{$path}", $payload);
}

describe('make effective', function () {
    it('brings an approved version into force and supersedes the one in force at the same instant', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $old] = releaseFixture();
        call($admin, 'postJson', "documents/{$document->uuid}/versions/{$old->uuid}/make-effective")->assertOk();
        ['version' => $new] = releaseFixture(document: $document, major: 2);

        $response = call($admin, 'postJson', "documents/{$document->uuid}/versions/{$new->uuid}/make-effective")->assertOk();

        expect($response->json())->toMatchArray(['success' => true, 'scheduled' => false, 'documentId' => $document->uuid, 'newEffectiveVersionId' => $new->uuid, 'supersededVersionId' => $old->uuid]);
        $old->refresh();
        $new->refresh();
        expect($new->status)->toBe(VersionStatus::Effective)->and($old->status)->toBe(VersionStatus::Superseded)
            ->and($old->superseded_at->equalTo($new->effective_at))->toBeTrue()
            ->and($document->fresh()->current_version_id)->toBe($new->id);
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::Released)->count())->toBe(2)
            ->and(DocumentEvent::query()->where('event_type', DocumentEventType::Superseded)->sole()->version_id)->toBe($old->id);
    });

    it('uses an explicit date that is not in the future as the effective date', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $version] = releaseFixture();

        $response = call($admin, 'postJson', "documents/{$document->uuid}/versions/{$version->uuid}/make-effective", ['effectiveAt' => '2026-01-05T00:00:00Z'])->assertOk();

        expect($response->json('effectiveAt'))->toBe('2026-01-05T00:00:00.000Z')->and($version->fresh()->effective_at->toDateString())->toBe('2026-01-05');
    });

    it('schedules a release more than a minute ahead and leaves the version approved', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $version] = releaseFixture();
        $when = now()->addDays(3)->startOfSecond();

        $response = call($admin, 'postJson', "documents/{$document->uuid}/versions/{$version->uuid}/make-effective", ['effectiveAt' => $when->toIso8601String()])->assertOk();

        expect($response->json())->toMatchArray(['success' => true, 'scheduled' => true]);
        $version->refresh();
        expect($version->status)->toBe(VersionStatus::Approved)->and($version->scheduled_effective_at->equalTo($when))->toBeTrue();
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::ReleaseScheduled)->count())->toBe(1);
        expect($this->notifier->sent[0]['recipients'])->toBe([$admin->id]);

        call($admin, 'postJson', "documents/{$document->uuid}/versions/{$version->uuid}/cancel-release")->assertOk()->assertExactJson(['success' => true]);
        expect($version->fresh()->scheduled_effective_at)->toBeNull();
        call($admin, 'postJson', "documents/{$document->uuid}/versions/{$version->uuid}/cancel-release")->assertOk();
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::ReleaseScheduleCancelled)->count())->toBe(1);
    });

    it('refuses versions that are not approved, with the Node messages', function (VersionStatus $status) {
        ['admin' => $admin, 'document' => $document, 'version' => $version] = releaseFixture($status);

        call($admin, 'postJson', "documents/{$document->uuid}/versions/{$version->uuid}/make-effective")->assertStatus(409)->assertJsonPath('error', "Illegal DCS state transition: {$status->value} → effective");
        call($admin, 'postJson', "documents/{$document->uuid}/versions/{$version->uuid}/make-effective", ['effectiveAt' => now()->addWeek()->toIso8601String()])
            ->assertStatus(409)->assertJsonPath('error', 'Hanya dokumen yang sudah disetujui penuh (status Approved) yang bisa dijadwalkan berlaku.');
        expect($version->fresh()->status)->toBe($status);
    })->with([VersionStatus::Draft, VersionStatus::UnderReview, VersionStatus::Effective, VersionStatus::Superseded]);

    it('rejects a malformed date, a foreign tenant, a version of another document and users without dcs.release', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $version] = releaseFixture();
        $foreign = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
        $viewer = User::factory()->for($admin->tenant)->role(SystemRole::Viewer)->create();
        ['document' => $other] = releaseFixture(tenant: $admin->tenant);
        $path = "documents/{$document->uuid}/versions/{$version->uuid}/make-effective";

        call($admin, 'postJson', $path, ['effectiveAt' => 'bukan tanggal'])->assertStatus(400)->assertJsonPath('error', 'Tanggal berlaku tidak valid.');
        call($foreign, 'postJson', $path)->assertNotFound();
        call($admin, 'postJson', "documents/{$other->uuid}/versions/{$version->uuid}/make-effective")->assertNotFound();
        call($viewer, 'postJson', $path)->assertForbidden();
        expect($version->fresh()->status)->toBe(VersionStatus::Approved);
    });
});

describe('withdraw', function () {
    it('retires the version in force without replacement', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $version] = releaseFixture();
        call($admin, 'postJson', "documents/{$document->uuid}/versions/{$version->uuid}/make-effective");

        call($admin, 'postJson', "documents/{$document->uuid}/versions/{$version->uuid}/withdraw")->assertOk()->assertJson(['success' => true, 'documentId' => $document->uuid, 'withdrawnVersionId' => $version->uuid]);

        expect($version->fresh()->status)->toBe(VersionStatus::Superseded)->and($version->fresh()->superseded_at)->not->toBeNull()->and($document->fresh()->current_version_id)->toBeNull();
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::Withdrawn)->count())->toBe(1);
    });

    it('refuses a version that is not in force', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $version] = releaseFixture();

        call($admin, 'postJson', "documents/{$document->uuid}/versions/{$version->uuid}/withdraw")->assertStatus(409)->assertJsonPath('error', 'Hanya dokumen berstatus Effective yang bisa dinonaktifkan.');
    });
});

describe('scheduler', function () {
    it('releases due versions at their scheduled moment and leaves the rest', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $due] = releaseFixture();
        $due->forceFill(['scheduled_effective_at' => now()->subHours(3)])->save();
        ['version' => $later] = releaseFixture(tenant: $admin->tenant);
        $later->forceFill(['scheduled_effective_at' => now()->addDay()])->save();
        ['version' => $manual] = releaseFixture(tenant: $admin->tenant);

        $this->artisan('dcs:release-due')->assertSuccessful();

        $due->refresh();
        expect($due->status)->toBe(VersionStatus::Effective)->and($due->effective_at->equalTo($due->scheduled_effective_at ?? $due->effective_at))->toBeTrue()
            ->and($due->effective_at->lessThan(now()->subHours(2)))->toBeTrue()->and($due->scheduled_effective_at)->toBeNull();
        expect($later->fresh()->status)->toBe(VersionStatus::Approved)->and($manual->fresh()->status)->toBe(VersionStatus::Approved);
        $event = DocumentEvent::query()->where('event_type', DocumentEventType::Released)->sole();
        expect($event->actor_type)->toBe(ActorType::System)->and($this->notifier->sent[0]['title'])->toBe('Dokumen Berlaku Otomatis (Terjadwal)');
    });

    it('sends each review reminder once a day, inside the window only', function () {
        $this->travelTo('2026-07-05 10:00:00');
        ['admin' => $admin, 'version' => $soon] = releaseFixture(VersionStatus::Effective);
        $soon->forceFill(['review_due_at' => '2026-07-20 00:00:00', 'review_reminder_days' => 30])->save();
        ['version' => $far] = releaseFixture(VersionStatus::Effective, $admin->tenant);
        $far->forceFill(['review_due_at' => '2026-12-01 00:00:00', 'review_reminder_days' => 30])->save();
        ['version' => $overdue] = releaseFixture(VersionStatus::Effective, $admin->tenant);
        $overdue->forceFill(['review_due_at' => '2026-07-01 00:00:00'])->save();
        ['version' => $draft] = releaseFixture(VersionStatus::Draft, $admin->tenant);
        $draft->forceFill(['review_due_at' => '2026-07-06 00:00:00'])->save();

        $this->artisan('dcs:review-reminders')->assertSuccessful();
        $this->artisan('dcs:review-reminders')->assertSuccessful();

        expect($this->notifier->sent)->toHaveCount(2);
        $byType = collect($this->notifier->sent)->keyBy('type');
        expect($byType['info']['message'])->toContain('dalam 15 hari')->and($byType['danger']['message'])->toContain('4 hari yang lalu')
            ->and($byType['danger']['title'])->toBe('Dokumen Lewat Jadwal Tinjau Ulang')->and($byType['info']['recipients'])->toContain($admin->id);

        $this->travelTo('2026-07-06 10:00:00');
        $this->artisan('dcs:review-reminders')->assertSuccessful();

        expect($this->notifier->sent)->toHaveCount(4);
    });
});

describe('view', function () {
    it('serves a watermarked copy from the live status and leaves a read receipt', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $version] = releaseFixture();
        call($admin, 'postJson', "documents/{$document->uuid}/versions/{$version->uuid}/make-effective");
        $reader = User::factory()->for($admin->tenant)->role(SystemRole::Viewer)->create();

        $response = call($reader, 'get', "versions/{$version->uuid}/view");

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-DCS-Copy-Status', 'effective')
            ->assertHeader('Content-Disposition', 'inline; filename="'.str_replace('/', '-', $document->document_number).'.pdf"');
        expect($response->headers->get('Cache-Control'))->toContain('no-store')->and($response->getContent())->toStartWith('%PDF');
        expect($this->engine->watermarked[0])->toMatchArray(['status' => 'effective', 'viewerName' => $reader->name, 'documentNumber' => $document->document_number, 'major' => 1, 'minor' => 0, 'expired' => false])
            ->and($this->engine->watermarked[0]['accessedAt'])->toEndWith(' UTC')->and($this->engine->watermarked[0]['issuedDateIso'])->not->toBeNull();
        expect(ReadReceipt::query()->where('user_id', $reader->id)->sole()->status)->toBe(ReceiptStatus::Read);
        call($reader, 'get', "versions/{$version->uuid}/view?download=1")->assertHeader('Content-Disposition', 'attachment; filename="'.str_replace('/', '-', $document->document_number).'.pdf"');
    });

    it('marks an effective version past its review date as expired', function () {
        ['admin' => $admin, 'version' => $version] = releaseFixture(VersionStatus::Effective);
        $version->forceFill(['review_due_at' => now()->subDay()])->save();

        call($admin, 'get', "versions/{$version->uuid}/view")->assertOk();

        expect($this->engine->watermarked[0]['expired'])->toBeTrue();
    });

    it('serves the English version only when a translation exists', function () {
        ['admin' => $admin, 'document' => $document, 'version' => $version] = releaseFixture(VersionStatus::Effective);

        call($admin, 'get', "versions/{$version->uuid}/view?lang=en")->assertNotFound()->assertJsonPath('error', 'Versi bahasa Inggris belum tersedia untuk dokumen ini.');

        app(StoreRendition::class)->handle($admin, $version, RenditionKind::Translated, FakePdfEngine::pdf('english'), language: 'en');
        call($admin, 'get', "versions/{$version->uuid}/view?lang=en")->assertOk()->assertHeader('Content-Disposition', 'inline; filename="'.str_replace('/', '-', $document->document_number).'-EN.pdf"');
    });

    it('refuses a missing file, a tampered file and an engine failure without leaving a receipt', function () {
        ['admin' => $admin, 'version' => $version] = releaseFixture(VersionStatus::Effective);

        Storage::disk('dcs')->put($version->currentRendition()->storage_key, FakePdfEngine::pdf('tampered'));
        call($admin, 'get', "versions/{$version->uuid}/view")->assertStatus(409)->assertJsonPath('error', 'Integritas dokumen gagal diverifikasi — akses diblokir. Hubungi Document Control.');

        ['version' => $empty] = releaseFixture(VersionStatus::Draft, $admin->tenant);
        $empty->renditions()->delete();
        call($admin, 'get', "versions/{$empty->uuid}/view")->assertStatus(409)->assertJsonPath('error', 'Versi ini belum memiliki berkas PDF');

        ['version' => $ok] = releaseFixture(VersionStatus::Effective, $admin->tenant);
        $this->engine->fail = true;
        $response = call($admin, 'get', "versions/{$ok->uuid}/view")->assertStatus(500);
        expect($response->json('error'))->toBe('Gagal menampilkan dokumen.')->and(ReadReceipt::query()->count())->toBe(0);
    });

    it('answers 404 for a foreign version', function () {
        ['version' => $version] = releaseFixture(VersionStatus::Effective);
        $foreign = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();

        call($foreign, 'get', "versions/{$version->uuid}/view")->assertNotFound();
    });
});

describe('distribution and receipts', function () {
    it('sends a version to users and never resets someone who already read it', function () {
        ['admin' => $admin, 'version' => $version] = releaseFixture(VersionStatus::Effective);
        $rina = User::factory()->for($admin->tenant)->role(SystemRole::Viewer)->create(['legacy_id' => 'usr-rina']);
        $budi = User::factory()->for($admin->tenant)->role(SystemRole::Viewer)->create(['legacy_id' => 'usr-budi']);

        $first = call($admin, 'postJson', "versions/{$version->uuid}/distribute", ['userIds' => ['usr-rina', 'usr-budi', 'usr-rina']])->assertOk();
        expect($first->json('count'))->toBe(2)->and($first->json('distributionId'))->toBeString();
        expect(ReadReceipt::query()->pluck('status')->unique()->all())->toBe([ReceiptStatus::Pending]);

        call($rina, 'get', "versions/{$version->uuid}/view")->assertOk();
        call($admin, 'postJson', "versions/{$version->uuid}/distribute", ['userIds' => ['usr-rina'], 'mandatory' => false])->assertOk();

        expect(ReadReceipt::query()->where('user_id', $rina->id)->sole()->status)->toBe(ReceiptStatus::Read);
        expect(ReadReceipt::query()->where('user_id', $budi->id)->sole()->status)->toBe(ReceiptStatus::Pending);
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::Distributed)->count())->toBe(2);
    });

    it('needs at least one known recipient from the tenant and dcs.release', function () {
        ['admin' => $admin, 'version' => $version] = releaseFixture(VersionStatus::Effective);
        User::factory()->for(Tenant::factory()->create())->create(['legacy_id' => 'usr-foreign']);
        $viewer = User::factory()->for($admin->tenant)->role(SystemRole::Viewer)->create();
        $path = "versions/{$version->uuid}/distribute";

        call($admin, 'postJson', $path, ['userIds' => []])->assertStatus(400)->assertJsonPath('error', 'Pilih minimal satu penerima');
        call($admin, 'postJson', $path)->assertStatus(400);
        call($admin, 'postJson', $path, ['userIds' => ['usr-foreign']])->assertStatus(400);
        call($viewer, 'postJson', $path, ['userIds' => ['x']])->assertForbidden();
        expect(ReadReceipt::query()->count())->toBe(0);
    });

    it('records an acknowledgement once and lists receipts in the Node shape', function () {
        ['admin' => $admin, 'version' => $version] = releaseFixture(VersionStatus::Effective);
        $rina = User::factory()->for($admin->tenant)->role(SystemRole::Viewer)->create(['legacy_id' => 'usr-rina']);
        call($admin, 'postJson', "versions/{$version->uuid}/distribute", ['userIds' => ['usr-rina']]);

        call($rina, 'postJson', "versions/{$version->uuid}/acknowledge")->assertOk()->assertExactJson(['success' => true]);
        $firstAck = ReadReceipt::query()->sole()->acknowledged_at;
        $this->travel(5)->minutes();
        call($rina, 'postJson', "versions/{$version->uuid}/acknowledge")->assertOk();
        call($rina, 'get', "versions/{$version->uuid}/view")->assertOk();

        $receipt = ReadReceipt::query()->sole();
        expect($receipt->status)->toBe(ReceiptStatus::Acknowledged)->and($receipt->acknowledged_at->equalTo($firstAck))->toBeTrue();
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::Acknowledged)->count())->toBe(1);

        $list = call($admin, 'getJson', "versions/{$version->uuid}/receipts")->assertOk()->json();
        expect($list)->toHaveCount(1)->and(array_keys($list[0]))->toBe(['userId', 'status', 'readAt', 'acknowledgedAt'])
            ->and($list[0]['userId'])->toBe('usr-rina')->and($list[0]['status'])->toBe('acknowledged');
    });
});
