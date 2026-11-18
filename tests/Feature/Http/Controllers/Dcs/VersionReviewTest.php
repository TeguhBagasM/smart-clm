<?php

use App\Actions\Dcs\StoreRendition;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Approval;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\Rendition;
use App\Models\Dcs\ReviewComment;
use App\Models\Dcs\VersionContent;
use App\Models\Dcs\VersionStep;
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
 * A version with a two-column signature sheet (Legal, then Admin), a composed
 * file, and the given status. The owner is an admin of the tenant.
 *
 * @return array{owner: User, legal: User, admin: User, version: DocumentVersion, tenant: Tenant}
 */
function reviewFixture(VersionStatus $status = VersionStatus::UnderReview, bool $composed = true): array
{
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->for($tenant)->role(SystemRole::Admin)->create();
    $legal = User::factory()->for($tenant)->role(SystemRole::Legal)->create();
    $admin = User::factory()->for($tenant)->role(SystemRole::Admin)->create();
    $document = Document::factory()->for($tenant)->create(['owner_user_id' => $owner->id, 'created_by_id' => $owner->id]);
    $version = DocumentVersion::factory()->for($document)->create(['status' => $status, 'created_by_id' => $owner->id]);

    foreach ([['Dibuat oleh', SystemRole::Legal], ['Disetujui oleh', SystemRole::Admin]] as $position => [$label, $role]) {
        VersionStep::query()->forceCreate(['version_id' => $version->id, 'group_position' => 1, 'group_heading' => null, 'position' => $position + 1, 'label' => $label, 'role_id' => systemRoleId($role), 'role_name' => null, 'assigned_user_id' => null, 'status' => 'pending']);
    }

    if ($composed) {
        VersionContent::query()->forceCreate(['version_id' => $version->id, 'language_mode' => 'single', 'flow_mode' => 'none', 'flow_display_style' => 'diagram', 'flow_lanes' => [], 'flow_steps' => [], 'render_layout' => ['signatureSheetPageIndex' => 1, 'sigLayout' => ['colW' => 170], 'headerLayout' => null], 'draft_revision' => 1]);
    }

    app(StoreRendition::class)->handle($owner, $version, $composed ? RenditionKind::Composed : RenditionKind::Uploaded, FakePdfEngine::pdf());

    return compact('owner', 'legal', 'admin', 'version', 'tenant');
}

function api(string $path, User $user, array $payload = [], string $method = 'postJson')
{
    return dcsAs($user)->{$method}("/api/dcs/{$path}", $payload);
}

describe('submit', function () {
    it('moves a draft into the signing queue and tells the first column', function () {
        ['owner' => $owner, 'legal' => $legal, 'version' => $version] = reviewFixture(VersionStatus::Draft);

        api("versions/{$version->uuid}/transition", $owner, ['to' => 'under_review'])->assertOk()->assertJson(['success' => true, 'from' => 'draft', 'to' => 'under_review']);

        expect($version->fresh()->status)->toBe(VersionStatus::UnderReview);
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::Submitted)->count())->toBe(1);
        expect($this->notifier->sent)->toHaveCount(1)->and($this->notifier->sent[0]['recipients'])->toBe([$legal->id]);
    });

    it('lets a user without dcs.edit submit, and refuses a version that is not a draft', function () {
        ['version' => $version, 'tenant' => $tenant] = reviewFixture(VersionStatus::Draft);
        $viewer = User::factory()->for($tenant)->role(SystemRole::Viewer)->create();

        api("versions/{$version->uuid}/transition", $viewer, ['to' => 'under_review'])->assertOk();
        api("versions/{$version->uuid}/transition", $viewer, ['to' => 'under_review'])->assertStatus(409);
    });

    it('only accepts under_review and draft as targets', function (string $to) {
        ['owner' => $owner, 'version' => $version] = reviewFixture(VersionStatus::Draft);

        api("versions/{$version->uuid}/transition", $owner, ['to' => $to])->assertStatus(400);

        expect($version->fresh()->status)->toBe(VersionStatus::Draft);
    })->with(['approved', 'effective', 'superseded', 'nonsense', '']);

    it('refuses to resubmit while the file still carries old signatures', function () {
        ['owner' => $owner, 'version' => $version] = reviewFixture(VersionStatus::Draft);
        app(StoreRendition::class)->handle($owner, $version, RenditionKind::Signed, FakePdfEngine::pdf('signed'));

        api("versions/{$version->uuid}/transition", $owner, ['to' => 'under_review'])->assertStatus(409);
    });

    it('answers 404 for a version of another tenant', function () {
        ['version' => $version] = reviewFixture(VersionStatus::Draft);
        $foreign = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();

        api("versions/{$version->uuid}/transition", $foreign, ['to' => 'under_review'])->assertNotFound();
    });
});

describe('sign-approve', function () {
    it('signs the columns in order and approves the version after the last one', function () {
        ['legal' => $legal, 'admin' => $admin, 'version' => $version, 'owner' => $owner] = reviewFixture();

        $first = api("versions/{$version->uuid}/sign-approve", $legal)->assertOk();
        expect($first->json())->toMatchArray(['success' => true, 'columnLabel' => 'Dibuat oleh', 'filled' => 1, 'total' => 2, 'complete' => false, 'promoted' => false]);
        expect($version->fresh()->status)->toBe(VersionStatus::UnderReview);
        expect($this->notifier->sent[0]['recipients'])->toContain($admin->id);

        $second = api("versions/{$version->uuid}/sign-approve", $admin)->assertOk();
        expect($second->json('complete'))->toBeTrue()->and($second->json('integritySha256'))->toHaveLength(64);
        expect($version->fresh()->status)->toBe(VersionStatus::Approved);
        expect(end($this->notifier->sent)['recipients'])->toBe([$owner->id]);

        expect(Approval::query()->where('version_id', $version->id)->orderBy('step_order')->pluck('column_label')->all())->toBe(['Dibuat oleh', 'Disetujui oleh']);
        expect(Rendition::query()->where('version_id', $version->id)->where('kind', RenditionKind::Signed)->count())->toBe(2);
        expect($version->fresh()->currentRendition()->sha256)->toBe($second->json('integritySha256'));
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::Signed)->count())->toBe(2);
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::Approved)->count())->toBe(1);
    });

    it('stamps the box of the signature sheet for a composed file and a generic block for an uploaded one', function () {
        ['legal' => $legal, 'version' => $version] = reviewFixture();
        api("versions/{$version->uuid}/sign-approve", $legal)->assertOk();

        expect($this->engine->stamped[0])->toMatchArray(['mode' => 'column', 'columnIndex' => 0, 'pageIndex' => 1, 'signerName' => $legal->name, 'signerRole' => 'legal'])
            ->and($this->engine->stamped[0]['groups'][0]['columns'][0])->toMatchArray(['label' => 'Dibuat oleh', 'role' => 'legal']);

        ['legal' => $legal2, 'version' => $uploaded] = reviewFixture(composed: false);
        api("versions/{$uploaded->uuid}/sign-approve", $legal2)->assertOk();

        expect($this->engine->stamped[1]['mode'])->toBe('generic');
    });

    it('signs a plain uploaded file with a single generic column anyone may sign', function () {
        ['tenant' => $tenant, 'version' => $version] = reviewFixture(composed: false);
        $version->steps()->delete();
        $viewer = User::factory()->for($tenant)->role(SystemRole::Viewer)->create();

        $response = api("versions/{$version->uuid}/sign-approve", $viewer)->assertOk();

        expect($response->json())->toMatchArray(['columnLabel' => 'Disetujui oleh', 'filled' => 1, 'total' => 1, 'complete' => true]);
        expect($version->fresh()->status)->toBe(VersionStatus::Approved);
    });

    it('refuses the wrong role, the wrong turn and users outside the column', function () {
        ['legal' => $legal, 'admin' => $admin, 'version' => $version, 'tenant' => $tenant] = reviewFixture();
        $viewer = User::factory()->for($tenant)->role(SystemRole::Viewer)->create();

        api("versions/{$version->uuid}/sign-approve", $admin)->assertForbidden()->assertJsonPath('error', 'Kolom "Dibuat oleh" memerlukan role "legal" — Anda login sebagai "admin".');
        api("versions/{$version->uuid}/sign-approve", $viewer)->assertForbidden();
        expect(Approval::query()->count())->toBe(0);

        $assignee = User::factory()->for($tenant)->role(SystemRole::Legal)->create();
        VersionStep::query()->where('version_id', $version->id)->where('position', 1)->update(['assigned_user_id' => $assignee->id]);

        api("versions/{$version->uuid}/sign-approve", $legal)->assertForbidden()->assertJsonPath('error', fn (string $e) => str_contains($e, 'ditunjuk untuk penandatangan tertentu'));
        api("versions/{$version->uuid}/sign-approve", $assignee)->assertOk();
    });

    it('lets a super admin sign any column', function () {
        ['version' => $version] = reviewFixture();
        $superAdmin = User::factory()->superAdmin()->create();

        // A super admin has no tenant; the version is looked up through the tenant they pick.
        $tenantUuid = $version->document->tenant->legacy_id ?? 't-'.$version->tenant_id;
        $version->document->tenant->forceFill(['legacy_id' => $tenantUuid])->save();

        dcsAs($superAdmin)->postJson("/api/dcs/versions/{$version->uuid}/sign-approve?tenantId={$tenantUuid}")->assertOk()->assertJsonPath('filled', 1);
    });

    it('refuses versions that cannot be signed', function () {
        ['legal' => $legal, 'version' => $draft] = reviewFixture(VersionStatus::Draft);
        api("versions/{$draft->uuid}/sign-approve", $legal)->assertStatus(409)->assertJsonPath('error', "Hanya versi berstatus 'Under Review' yang bisa ditandatangani.");

        ['legal' => $legal, 'version' => $noFile] = reviewFixture();
        Rendition::query()->where('version_id', $noFile->id)->delete();
        api("versions/{$noFile->uuid}/sign-approve", $legal)->assertStatus(409)->assertJsonPath('error', 'Versi ini belum memiliki berkas PDF untuk ditandatangani.');

        ['legal' => $legal, 'admin' => $admin, 'version' => $done] = reviewFixture();
        api("versions/{$done->uuid}/sign-approve", $legal)->assertOk();
        api("versions/{$done->uuid}/sign-approve", $admin)->assertOk();
        api("versions/{$done->uuid}/sign-approve", $admin)->assertStatus(409);
    });

    it('refuses a file whose bytes no longer match the recorded hash', function () {
        ['legal' => $legal, 'version' => $version] = reviewFixture();
        Storage::disk('dcs')->put($version->currentRendition()->storage_key, FakePdfEngine::pdf('tampered'));

        api("versions/{$version->uuid}/sign-approve", $legal)->assertStatus(409)->assertJsonPath('error', 'Integritas berkas gagal diverifikasi sebelum tanda tangan — dibatalkan.');

        expect($this->engine->stamped)->toBeEmpty()->and(Approval::query()->count())->toBe(0);
    });

    it('stores nothing when the engine fails and answers 500 without details', function () {
        ['legal' => $legal, 'version' => $version] = reviewFixture();
        $this->engine->fail = true;

        $response = api("versions/{$version->uuid}/sign-approve", $legal)->assertStatus(500);

        expect($response->json('error'))->toBe('Gagal menandatangani & menyetujui versi.')->and($response->getContent())->not->toContain('boom');
        expect(Approval::query()->count())->toBe(0)->and(Rendition::query()->where('kind', RenditionKind::Signed)->count())->toBe(0);
    });
});

describe('request changes', function () {
    it('returns the version to draft, voids the signatures and keeps the note', function () {
        ['legal' => $legal, 'admin' => $admin, 'owner' => $owner, 'version' => $version] = reviewFixture();
        api("versions/{$version->uuid}/sign-approve", $legal)->assertOk();

        $response = api("versions/{$version->uuid}/request-changes", $admin, ['body' => 'Perbaiki bab 2', 'anchor' => ['field' => 'section', 'sectionIndex' => '1', 'quote' => 'x']])->assertOk();

        expect($response->json())->toMatchArray(['success' => true, 'signaturesReset' => 1, 'returnedTo' => 'draft'])
            ->and($response->json('comment.body'))->toBe('Perbaiki bab 2')->and($response->json('comment.anchor.sectionIndex'))->toBe(1)->and($response->json('comment.status'))->toBe('open');
        $fresh = $version->fresh();
        expect($fresh->status)->toBe(VersionStatus::Draft)->and($fresh->review_round)->toBe(2);
        expect(Approval::query()->count())->toBe(1)->and(Approval::query()->whereNull('voided_at')->count())->toBe(0);
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::ChangesRequested)->count())->toBe(1);
        expect(end($this->notifier->sent))->toMatchArray(['recipients' => [$owner->id], 'type' => 'warning']);
    });

    it('needs a note, the right turn and an under review version', function () {
        ['legal' => $legal, 'admin' => $admin, 'version' => $version] = reviewFixture();

        api("versions/{$version->uuid}/request-changes", $legal, ['body' => '  '])->assertStatus(400)->assertJsonPath('error', 'Sertakan alasan/catatan revisi agar penyusun tahu yang harus diperbaiki.');
        api("versions/{$version->uuid}/request-changes", $admin, ['body' => 'x'])->assertForbidden();
        expect($version->fresh()->status)->toBe(VersionStatus::UnderReview);

        ['owner' => $owner, 'version' => $draft] = reviewFixture(VersionStatus::Draft);
        api("versions/{$draft->uuid}/request-changes", $owner, ['body' => 'x'])->assertStatus(409);
    });

    it('lets the author resubmit after recomposing and the chain starts over', function () {
        ['legal' => $legal, 'admin' => $admin, 'owner' => $owner, 'version' => $version] = reviewFixture();
        api("versions/{$version->uuid}/sign-approve", $legal)->assertOk();
        api("versions/{$version->uuid}/request-changes", $admin, ['body' => 'x'])->assertOk();

        api("versions/{$version->uuid}/transition", $owner, ['to' => 'under_review'])->assertStatus(409);

        app(StoreRendition::class)->handle($owner, $version, RenditionKind::Composed, FakePdfEngine::pdf('v2'));
        api("versions/{$version->uuid}/transition", $owner, ['to' => 'under_review'])->assertOk();

        expect(api("versions/{$version->uuid}/approval-roster", $owner, method: 'getJson')->json('*.status'))->toBe(['pending', 'pending']);
        api("versions/{$version->uuid}/sign-approve", $legal)->assertOk()->assertJsonPath('filled', 1);
        expect(Approval::query()->where('step_order', 1)->count())->toBe(2);
    });
});

describe('return to draft', function () {
    it('is limited to the author and document control, and voids the signatures', function () {
        ['legal' => $legal, 'owner' => $owner, 'version' => $version, 'tenant' => $tenant] = reviewFixture();
        api("versions/{$version->uuid}/sign-approve", $legal)->assertOk();
        $viewer = User::factory()->for($tenant)->role(SystemRole::Viewer)->create();

        api("versions/{$version->uuid}/transition", $viewer, ['to' => 'draft'])->assertForbidden();
        api("versions/{$version->uuid}/transition", $owner, ['to' => 'draft'])->assertOk()->assertJson(['from' => 'under_review', 'to' => 'draft']);

        expect($version->fresh()->status)->toBe(VersionStatus::Draft)->and(Approval::query()->whereNull('voided_at')->count())->toBe(0);
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::ReturnedToDraft)->count())->toBe(1);
    });
});

describe('comments', function () {
    it('adds, lists and resolves comments, and tells the author', function () {
        ['legal' => $legal, 'owner' => $owner, 'version' => $version] = reviewFixture();

        $added = api("versions/{$version->uuid}/comments", $legal, ['body' => '  Typo di tujuan ', 'anchor' => ['field' => 'bogus', 'quote' => '   ']])->assertOk();
        expect($added->json('comment'))->toMatchArray(['body' => 'Typo di tujuan', 'authorName' => $legal->name, 'authorRole' => 'legal', 'status' => 'open', 'reviewRound' => 1])
            ->and($added->json('comment.anchor'))->toBe(['field' => 'general']);
        expect($this->notifier->sent[0]['recipients'])->toBe([$owner->id]);

        api("versions/{$version->uuid}/comments", $owner, ['body' => 'Catatan sendiri'])->assertOk();
        expect($this->notifier->sent)->toHaveCount(1);

        expect(api("versions/{$version->uuid}/comments", $legal, method: 'getJson')->json('*.body'))->toBe(['Typo di tujuan', 'Catatan sendiri']);

        $cid = $added->json('comment.id');
        api("versions/{$version->uuid}/comments/{$cid}/resolve", $legal)->assertForbidden();
        $resolved = api("versions/{$version->uuid}/comments/{$cid}/resolve", $owner)->assertOk();
        expect($resolved->json('comment.status'))->toBe('resolved')->and($resolved->json('comment.resolvedByName'))->toBe($owner->name)->and($resolved->json('openLeft'))->toBe(1);
        api("versions/{$version->uuid}/comments/{$cid}/resolve", $owner)->assertNotFound()->assertJsonPath('error', 'Komentar tidak ditemukan atau sudah ditandai selesai.');
        expect(ReviewComment::query()->where('uuid', $cid)->sole()->resolved_by_id)->toBe($owner->id);
    });

    it('needs a body, a draft or under review version, and a comment of that version', function () {
        ['legal' => $legal, 'owner' => $owner, 'version' => $version] = reviewFixture();
        api("versions/{$version->uuid}/comments", $legal, ['body' => ''])->assertStatus(400)->assertJsonPath('error', 'Isi komentar tidak boleh kosong.');

        $other = reviewFixture()['version'];
        $foreignComment = api("versions/{$other->uuid}/comments", $legal, ['body' => 'x']);
        expect($foreignComment->status())->toBe(404);

        ['owner' => $owner2, 'version' => $approved] = reviewFixture(VersionStatus::Approved);
        api("versions/{$approved->uuid}/comments", $owner2, ['body' => 'x'])->assertStatus(409);
    });
});

describe('approvals and roster', function () {
    it('lists signatures and who is still to sign in the Node shape', function () {
        ['legal' => $legal, 'owner' => $owner, 'version' => $version] = reviewFixture();
        $assignee = User::factory()->for($legal->tenant)->role(SystemRole::Admin)->create();
        VersionStep::query()->where('version_id', $version->id)->where('position', 2)->update(['assigned_user_id' => $assignee->id]);
        api("versions/{$version->uuid}/sign-approve", $legal)->assertOk();

        $approvals = api("versions/{$version->uuid}/approvals", $owner, method: 'getJson')->assertOk()->json();
        expect($approvals)->toHaveCount(1)->and(array_keys($approvals[0]))->toBe(['approverUserId', 'approverRole', 'decision', 'decidedAt', 'signedContentSha256'])
            ->and($approvals[0]['approverUserId'])->toBe($legal->fresh()->apiId())->and($approvals[0]['approverRole'])->toBe('legal');

        $roster = api("versions/{$version->uuid}/approval-roster", $owner, method: 'getJson')->assertOk()->json();
        expect($roster[0])->toMatchArray(['order' => 1, 'label' => 'Dibuat oleh', 'role' => 'legal', 'status' => 'approved'])
            ->and($roster[1])->toMatchArray(['order' => 2, 'label' => 'Disetujui oleh', 'role' => 'admin', 'status' => 'pending', 'assignedUserId' => $assignee->fresh()->apiId()])
            ->and($roster[1])->not->toHaveKey('approverUserId');
    });

    it('answers 404 for a foreign version', function () {
        ['version' => $version] = reviewFixture();
        $foreign = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();

        api("versions/{$version->uuid}/approvals", $foreign, method: 'getJson')->assertNotFound();
        api("versions/{$version->uuid}/comments", $foreign, method: 'getJson')->assertNotFound();
    });
});

it('keeps a document that was ever signed from releasing its number', function () {
    $owner = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
    $legal = User::factory()->for($owner->tenant)->role(SystemRole::Legal)->create();
    $created = api('documents', $owner, ['docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Prosedur Cuti'])->assertOk();
    $version = DocumentVersion::query()->where('uuid', $created->json('versionId'))->sole();
    VersionStep::query()->forceCreate(['version_id' => $version->id, 'group_position' => 1, 'position' => 1, 'label' => 'Dibuat oleh', 'role_id' => systemRoleId(SystemRole::Legal), 'status' => 'pending']);
    VersionStep::query()->forceCreate(['version_id' => $version->id, 'group_position' => 1, 'position' => 2, 'label' => 'Disetujui oleh', 'role_id' => systemRoleId(SystemRole::Admin), 'status' => 'pending']);
    app(StoreRendition::class)->handle($owner, $version, RenditionKind::Uploaded, FakePdfEngine::pdf());

    api("versions/{$version->uuid}/transition", $owner, ['to' => 'under_review'])->assertOk();
    api("versions/{$version->uuid}/sign-approve", $legal)->assertOk();
    api("versions/{$version->uuid}/transition", $owner, ['to' => 'draft'])->assertOk();

    api("documents/{$created->json('documentId')}/release-number", $owner)->assertStatus(400)->assertJsonPath('error', 'Nomor tidak bisa dilepas: dokumen ini sudah pernah ditandatangani.');
});
