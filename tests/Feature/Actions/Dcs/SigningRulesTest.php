<?php

use App\Actions\Dcs\AddReviewComment;
use App\Actions\Dcs\CreateDocument;
use App\Actions\Dcs\ReleaseDocumentNumber;
use App\Actions\Dcs\RequestVersionChanges;
use App\Actions\Dcs\ResolveReviewComment;
use App\Actions\Dcs\ReturnVersionToDraft;
use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Actions\Dcs\SignVersion;
use App\Actions\Dcs\StoreRendition;
use App\Actions\Dcs\SubmitVersionForReview;
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
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Notifications\DcsNotifier;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakePdfEngine;
use Tests\Support\RecordingNotifier;

/*
 * The rules of submitting and signing, held at the Action (these used to be checked only
 * through the legacy Node-style API).
 */

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    $this->engine = new FakePdfEngine;
    app()->instance(PdfEngine::class, $this->engine);
    $this->notifier = new RecordingNotifier;
    app()->instance(DcsNotifier::class, $this->notifier);
});

/**
 * A version with a two-column signing sheet (Legal, then Admin), a file and the given status.
 *
 * @return array{owner: User, legal: User, admin: User, version: DocumentVersion, tenant: Tenant}
 */
function signFixture(VersionStatus $status = VersionStatus::UnderReview, bool $composed = true): array
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

function sign(User $user, DocumentVersion $version): array
{
    return app(SignVersion::class)->handle($user, $version->fresh());
}

function submit(User $user, DocumentVersion $version): array
{
    return app(SubmitVersionForReview::class)->handle($user, $version->fresh());
}

it('refuses to resubmit while the file still carries old signatures', function () {
    ['owner' => $owner, 'version' => $version] = signFixture(VersionStatus::Draft);
    app(StoreRendition::class)->handle($owner, $version, RenditionKind::Signed, FakePdfEngine::pdf('signed'));

    expect(fn () => submit($owner, $version))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(409));
    expect($version->fresh()->status)->toBe(VersionStatus::Draft);
});

it('stamps the box of the signature sheet for a composed file and a generic block for an uploaded one', function () {
    ['legal' => $legal, 'version' => $version] = signFixture();
    sign($legal, $version);

    expect($this->engine->stamped[0])->toMatchArray(['mode' => 'column', 'columnIndex' => 0, 'pageIndex' => 1, 'signerName' => $legal->name, 'signerRole' => 'legal'])
        ->and($this->engine->stamped[0]['groups'][0]['columns'][0])->toMatchArray(['label' => 'Dibuat oleh', 'role' => 'legal']);

    ['legal' => $legal2, 'version' => $uploaded] = signFixture(composed: false);
    sign($legal2, $uploaded);

    expect($this->engine->stamped[1]['mode'])->toBe('generic');
});

it('signs a plain uploaded file with a single generic column anyone may sign', function () {
    ['tenant' => $tenant, 'version' => $version] = signFixture(composed: false);
    $version->steps()->delete();
    $viewer = User::factory()->for($tenant)->role(SystemRole::Viewer)->create();

    $result = sign($viewer, $version);

    expect($result)->toMatchArray(['columnLabel' => 'Disetujui oleh', 'filled' => 1, 'total' => 1, 'complete' => true])->and($version->fresh()->status)->toBe(VersionStatus::Approved);
});

it('lets a super admin sign any column, through the document page', function () {
    ['version' => $version] = signFixture();
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)->post(route('documents.versions.sign', [$version->document, $version]))->assertRedirect();

    expect(Approval::query()->where('version_id', $version->id)->count())->toBe(1);
});

it('refuses a file whose bytes no longer match the recorded hash, and stamps nothing', function () {
    ['legal' => $legal, 'version' => $version] = signFixture();
    Storage::disk('dcs')->put($version->currentRendition()->storage_key, FakePdfEngine::pdf('tampered'));

    expect(fn () => sign($legal, $version))->toThrow(DcsException::class, 'Integritas berkas gagal diverifikasi sebelum tanda tangan — dibatalkan.');

    expect($this->engine->stamped)->toBeEmpty()->and(Approval::query()->count())->toBe(0);
});

it('lets the author resubmit after recomposing and the chain starts over', function () {
    ['legal' => $legal, 'admin' => $admin, 'owner' => $owner, 'version' => $version] = signFixture();
    sign($legal, $version);
    app(RequestVersionChanges::class)->handle($admin, $version->fresh(), 'x', ['field' => 'general']);

    expect(fn () => submit($owner, $version))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(409));

    app(StoreRendition::class)->handle($owner, $version, RenditionKind::Composed, FakePdfEngine::pdf('v2'));
    submit($owner, $version);

    expect($version->fresh()->status)->toBe(VersionStatus::UnderReview)->and(Approval::query()->whereNull('voided_at')->count())->toBe(0);
    expect(sign($legal, $version)['filled'])->toBe(1)->and(Approval::query()->where('step_order', 1)->count())->toBe(2);
});

it('keeps a document that was ever signed from releasing its number', function () {
    $tenant = Tenant::factory()->create();
    app(SeedDcsTenantDefaults::class)->handle($tenant);
    $owner = User::factory()->for($tenant)->role(SystemRole::Admin)->create();
    $legal = User::factory()->for($tenant)->role(SystemRole::Legal)->create();
    $created = app(CreateDocument::class)->handle($owner, $tenant, ['docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Prosedur Cuti', 'ownerUserId' => null, 'manualNumber' => null, 'archiveCategory' => null, 'archiveSubFolderId' => null]);
    $version = $created['version'];
    VersionStep::query()->forceCreate(['version_id' => $version->id, 'group_position' => 1, 'position' => 1, 'label' => 'Dibuat oleh', 'role_id' => systemRoleId(SystemRole::Legal), 'status' => 'pending']);
    VersionStep::query()->forceCreate(['version_id' => $version->id, 'group_position' => 1, 'position' => 2, 'label' => 'Disetujui oleh', 'role_id' => systemRoleId(SystemRole::Admin), 'status' => 'pending']);
    app(StoreRendition::class)->handle($owner, $version, RenditionKind::Uploaded, FakePdfEngine::pdf());

    submit($owner, $version);
    sign($legal, $version);
    app(ReturnVersionToDraft::class)->handle($owner, $version->fresh());

    expect(fn () => app(ReleaseDocumentNumber::class)->handle($owner, $created['document']->fresh()))
        ->toThrow(DcsException::class, 'Nomor tidak bisa dilepas: dokumen ini sudah pernah ditandatangani.');
});

it('moves a draft into the signing queue, records it and tells the first column', function () {
    ['owner' => $owner, 'legal' => $legal, 'version' => $version] = signFixture(VersionStatus::Draft);

    $result = submit($owner, $version);

    expect($result)->toMatchArray(['from' => 'draft', 'to' => 'under_review'])->and($version->fresh()->status)->toBe(VersionStatus::UnderReview)
        ->and(DocumentEvent::query()->where('event_type', DocumentEventType::Submitted)->count())->toBe(1)
        ->and($this->notifier->sent)->toHaveCount(1)->and($this->notifier->sent[0]['recipients'])->toBe([$legal->id]);
});

it('refuses someone outside the column, and a column named for another person, with the reason', function () {
    ['legal' => $legal, 'version' => $version, 'tenant' => $tenant] = signFixture();
    $viewer = User::factory()->for($tenant)->role(SystemRole::Viewer)->create();

    expect(fn () => sign($viewer, $version))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(403));

    $assignee = User::factory()->for($tenant)->role(SystemRole::Legal)->create();
    VersionStep::query()->where('version_id', $version->id)->where('position', 1)->update(['assigned_user_id' => $assignee->id]);

    expect(fn () => sign($legal, $version))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(403)->and($e->getMessage())->toContain('ditunjuk untuk penandatangan tertentu'));
    expect(Approval::query()->count())->toBe(0);

    sign($assignee, $version);
    expect(Approval::query()->count())->toBe(1);
});

describe('submitting', function () {
    it('lets anyone submit a draft, and refuses a version that is not a draft', function () {
        ['version' => $version, 'tenant' => $tenant] = signFixture(VersionStatus::Draft);
        $viewer = User::factory()->for($tenant)->role(SystemRole::Viewer)->create();

        submit($viewer, $version);

        expect($version->fresh()->status)->toBe(VersionStatus::UnderReview)
            ->and(fn () => submit($viewer, $version))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(409));
    });

    it('refuses a draft without a file', function () {
        ['owner' => $owner, 'version' => $version] = signFixture(VersionStatus::Draft);
        $version->renditions()->delete();

        expect(fn () => submit($owner, $version))->toThrow(DcsException::class, 'Belum ada berkas PDF. Susun atau unggah PDF sebelum mengajukan untuk tanda tangan.');
    });
});

describe('signing', function () {
    it('signs the columns in order, stores a signed file each time and approves after the last', function () {
        ['legal' => $legal, 'admin' => $admin, 'owner' => $owner, 'version' => $version] = signFixture();

        $first = sign($legal, $version);
        expect($first)->toMatchArray(['columnLabel' => 'Dibuat oleh', 'filled' => 1, 'total' => 2, 'complete' => false])->and($version->fresh()->status)->toBe(VersionStatus::UnderReview)
            ->and($this->notifier->sent[0]['recipients'])->toContain($admin->id);

        $second = sign($admin, $version);

        expect($second['complete'])->toBeTrue()->and($second['integritySha256'])->toHaveLength(64)->and($version->fresh()->status)->toBe(VersionStatus::Approved)
            ->and(end($this->notifier->sent)['recipients'])->toBe([$owner->id])
            ->and(Approval::query()->where('version_id', $version->id)->orderBy('step_order')->pluck('column_label')->all())->toBe(['Dibuat oleh', 'Disetujui oleh'])
            ->and(Rendition::query()->where('version_id', $version->id)->where('kind', RenditionKind::Signed)->count())->toBe(2)
            ->and($version->fresh()->currentRendition()->sha256)->toBe($second['integritySha256'])
            ->and(DocumentEvent::query()->where('event_type', DocumentEventType::Signed)->count())->toBe(2)
            ->and(DocumentEvent::query()->where('event_type', DocumentEventType::Approved)->count())->toBe(1);
    });

    it('refuses versions that cannot be signed, with the reason', function () {
        ['legal' => $legal, 'admin' => $admin, 'version' => $draft] = signFixture(VersionStatus::Draft);
        expect(fn () => sign($legal, $draft))->toThrow(DcsException::class, "Hanya versi berstatus 'Under Review' yang bisa ditandatangani.");

        ['legal' => $legal2, 'version' => $noFile] = signFixture();
        Rendition::query()->where('version_id', $noFile->id)->delete();
        expect(fn () => sign($legal2, $noFile))->toThrow(DcsException::class, 'Versi ini belum memiliki berkas PDF untuk ditandatangani.');

        ['legal' => $legal3, 'admin' => $admin3, 'version' => $done] = signFixture();
        sign($legal3, $done);
        sign($admin3, $done);
        expect(fn () => sign($admin3, $done))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(409));
    });

    it('stores nothing when the engine fails and hides the details', function () {
        ['legal' => $legal, 'version' => $version] = signFixture();
        $this->engine->fail = true;

        expect(fn () => sign($legal, $version))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(500)->and($e->getMessage())->toBe('Gagal menandatangani & menyetujui versi.')->and($e->getMessage())->not->toContain('boom'));

        expect(Approval::query()->count())->toBe(0)->and(Rendition::query()->where('kind', RenditionKind::Signed)->count())->toBe(0);
    });
});

describe('sending a version back', function () {
    it('returns it to draft, voids the signatures, keeps the note and tells the author', function () {
        ['legal' => $legal, 'admin' => $admin, 'owner' => $owner, 'version' => $version] = signFixture();
        sign($legal, $version);

        $result = app(RequestVersionChanges::class)->handle($admin, $version->fresh(), 'Perbaiki bab 2', ['field' => 'section', 'sectionIndex' => 1, 'quote' => 'x']);

        $fresh = $version->fresh();
        expect($result)->toMatchArray(['signaturesReset' => 1])->and($fresh->status)->toBe(VersionStatus::Draft)->and($fresh->review_round)->toBe(2)
            ->and(Approval::query()->count())->toBe(1)->and(Approval::query()->whereNull('voided_at')->count())->toBe(0)
            ->and(DocumentEvent::query()->where('event_type', DocumentEventType::ChangesRequested)->count())->toBe(1)
            ->and(end($this->notifier->sent))->toMatchArray(['recipients' => [$owner->id], 'type' => 'warning']);
        $comment = ReviewComment::query()->sole();
        expect($comment->body)->toBe('Perbaiki bab 2')->and($comment->status->value)->toBe('open')->and($comment->anchor['sectionIndex'])->toBe(1);
    });

    it('is limited to the column whose turn it is and to a version under review', function () {
        ['admin' => $admin, 'version' => $version] = signFixture();
        expect(fn () => app(RequestVersionChanges::class)->handle($admin, $version->fresh(), 'x', ['field' => 'general']))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(403));
        expect($version->fresh()->status)->toBe(VersionStatus::UnderReview);

        ['owner' => $owner, 'version' => $draft] = signFixture(VersionStatus::Draft);
        expect(fn () => app(RequestVersionChanges::class)->handle($owner, $draft->fresh(), 'x', ['field' => 'general']))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(409));
    });

    it('lets only the author or document control pull a version back, and voids the signatures', function () {
        ['legal' => $legal, 'owner' => $owner, 'version' => $version, 'tenant' => $tenant] = signFixture();
        sign($legal, $version);
        $viewer = User::factory()->for($tenant)->role(SystemRole::Viewer)->create();

        expect(fn () => app(ReturnVersionToDraft::class)->handle($viewer, $version->fresh()))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(403));

        $result = app(ReturnVersionToDraft::class)->handle($owner, $version->fresh());

        expect($result)->toMatchArray(['from' => 'under_review', 'to' => 'draft'])->and($version->fresh()->status)->toBe(VersionStatus::Draft)->and(Approval::query()->whereNull('voided_at')->count())->toBe(0)
            ->and(DocumentEvent::query()->where('event_type', DocumentEventType::ReturnedToDraft)->count())->toBe(1);
    });
});

describe('review comments', function () {
    it('adds a comment with a clean anchor and tells the author, but not about their own comment', function () {
        ['legal' => $legal, 'owner' => $owner, 'version' => $version] = signFixture();

        $comment = app(AddReviewComment::class)->handle($legal, $version->fresh(), 'Typo di tujuan', ['field' => 'general']);

        expect($comment->body)->toBe('Typo di tujuan')->and($comment->author_name)->toBe($legal->name)->and($comment->author_role_key)->toBe('legal')->and($comment->status->value)->toBe('open')->and($comment->review_round)->toBe(1)
            ->and($this->notifier->sent[0]['recipients'])->toBe([$owner->id]);

        app(AddReviewComment::class)->handle($owner, $version->fresh(), 'Catatan sendiri', ['field' => 'general']);

        expect($this->notifier->sent)->toHaveCount(1);
    });

    it('lets only the author mark a comment done, once', function () {
        ['legal' => $legal, 'owner' => $owner, 'version' => $version] = signFixture();
        $comment = app(AddReviewComment::class)->handle($legal, $version->fresh(), 'Typo', ['field' => 'general']);
        app(AddReviewComment::class)->handle($legal, $version->fresh(), 'Satu lagi', ['field' => 'general']);

        expect(fn () => app(ResolveReviewComment::class)->handle($legal, $version->fresh(), $comment->uuid))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(403));

        $resolved = app(ResolveReviewComment::class)->handle($owner, $version->fresh(), $comment->uuid);

        expect($resolved['openLeft'])->toBe(1)->and($comment->fresh()->resolved_by_id)->toBe($owner->id)->and($comment->fresh()->status->value)->toBe('resolved');
        expect(fn () => app(ResolveReviewComment::class)->handle($owner, $version->fresh(), $comment->uuid))->toThrow(DcsException::class, 'Komentar tidak ditemukan atau sudah ditandai selesai.');
    });

    it('takes comments only while a version is a draft or under review', function () {
        ['owner' => $owner, 'version' => $approved] = signFixture(VersionStatus::Approved);

        expect(fn () => app(AddReviewComment::class)->handle($owner, $approved->fresh(), 'x', ['field' => 'general']))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(409));
    });
});
