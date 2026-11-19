<?php

use App\Actions\Dcs\StoreRendition;
use App\Enums\Dcs\ReceiptStatus;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Approval;
use App\Models\Dcs\Department;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ReadReceipt;
use App\Models\Dcs\ReviewComment;
use App\Models\Dcs\VersionStep;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Notifications\DcsNotifier;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePdfEngine;
use Tests\Support\RecordingNotifier;

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    app()->instance(PdfEngine::class, new FakePdfEngine);
    app()->instance(DcsNotifier::class, new RecordingNotifier);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
    $this->legal = User::factory()->for($this->tenant)->role(SystemRole::Legal)->create();
    $this->viewer = User::factory()->for($this->tenant)->role(SystemRole::Viewer)->create();
});

/**
 * @param  array<string, mixed>  $document
 * @param  array<string, mixed>  $version
 * @param  array<int, array{0: string, 1: ?SystemRole, 2: ?User}>  $columns  label, role, assigned user
 */
function queueVersion(VersionStatus $status, array $document = [], array $version = [], array $columns = []): DocumentVersion
{
    $owner = $document['owner'] ?? test()->admin;
    unset($document['owner']);
    $doc = Document::factory()->for(test()->tenant)->create(['owner_user_id' => $owner->id, 'created_by_id' => $owner->id, ...$document]);
    $created = DocumentVersion::factory()->for($doc)->create(['status' => $status, 'created_by_id' => $owner->id, ...$version]);

    foreach ($columns as $position => [$label, $role, $assigned]) {
        VersionStep::query()->forceCreate(['version_id' => $created->id, 'group_position' => 1, 'position' => $position + 1, 'label' => $label, 'role_id' => $role ? systemRoleId($role) : null, 'assigned_user_id' => $assigned?->id, 'status' => 'pending']);
    }

    return $created;
}

function queuePage(User $user, array $query = []): array
{
    $props = [];
    test()->actingAs($user)->get(route('documents.queue', $query))->assertOk()->assertInertia(function (Assert $page) use (&$props) {
        $props = $page->toArray()['props'];
    });

    return $props;
}

function queueNumbers(array $props): array
{
    return collect($props['items']['data'])->pluck('document.number')->all();
}

describe('signing tasks', function () {
    it('lists a version when the column whose turn it is is assigned to me or held by my role', function () {
        queueVersion(VersionStatus::UnderReview, ['document_number' => 'BY-ROLE'], columns: [['Dibuat oleh', SystemRole::Legal, null]]);
        queueVersion(VersionStatus::UnderReview, ['document_number' => 'ASSIGNED'], columns: [['Dibuat oleh', SystemRole::Admin, $this->legal]]);
        queueVersion(VersionStatus::UnderReview, ['document_number' => 'OTHER-ROLE'], columns: [['Dibuat oleh', SystemRole::Admin, null]]);
        queueVersion(VersionStatus::UnderReview, ['document_number' => 'OTHER-PERSON'], columns: [['Dibuat oleh', SystemRole::Legal, $this->admin]]);
        queueVersion(VersionStatus::UnderReview, ['document_number' => 'ANYONE'], columns: [['Dibuat oleh', null, null]]);
        queueVersion(VersionStatus::Draft, ['document_number' => 'NOT-IN-REVIEW'], columns: [['Dibuat oleh', SystemRole::Legal, null]]);

        $props = queuePage($this->legal, ['kind' => 'sign']);

        expect(queueNumbers($props))->toEqualCanonicalizing(['BY-ROLE', 'ASSIGNED']);
        expect(queueNumbers(queuePage($this->admin, ['kind' => 'sign'])))->toEqualCanonicalizing(['OTHER-ROLE', 'OTHER-PERSON']);
        expect(queueNumbers(queuePage($this->viewer, ['kind' => 'sign'])))->toBe([]);
    });

    it('moves to the next signer once a column is signed, and says which column it is', function () {
        $version = queueVersion(VersionStatus::UnderReview, ['document_number' => 'TWO'], columns: [['Dibuat oleh', SystemRole::Legal, null], ['Disetujui oleh', SystemRole::Admin, null]]);

        expect(queueNumbers(queuePage($this->admin, ['kind' => 'sign'])))->toBe([]);
        $rendition = app(StoreRendition::class)->handle($this->admin, $version, RenditionKind::Composed, FakePdfEngine::pdf());
        Approval::query()->forceCreate(['version_id' => $version->id, 'step_order' => 1, 'column_label' => 'Dibuat oleh', 'approver_user_id' => $this->legal->id, 'approver_role_key' => 'legal', 'signed_content_sha256' => $rendition->sha256, 'rendition_id' => $rendition->id, 'review_round' => 1, 'decided_at' => now()]);

        $props = queuePage($this->admin, ['kind' => 'sign']);
        expect(queueNumbers($props))->toBe(['TWO'])->and($props['items']['data'][0]['detail'])->toBe('Kolom "Disetujui oleh" (giliran 2 dari 2)');
        expect(queueNumbers(queuePage($this->legal, ['kind' => 'sign'])))->toBe([]);
    });

    it('counts the wait from the last signature, not only from the submission', function () {
        $this->travelTo('2026-07-01 10:00:00');
        $version = queueVersion(VersionStatus::UnderReview, columns: [['Dibuat oleh', SystemRole::Legal, null], ['Disetujui oleh', SystemRole::Admin, null]]);
        app(StoreRendition::class)->handle($this->admin, $version, RenditionKind::Composed, FakePdfEngine::pdf());
        $before = $version->fresh()->updated_at;
        $this->travelTo('2026-07-05 10:00:00');

        dcsAs($this->legal)->postJson('/api/dcs/versions/'.$version->uuid.'/sign-approve')->assertOk();

        expect($version->fresh()->updated_at->greaterThan($before))->toBeTrue();
    });
});

describe('other tasks', function () {
    it('lists drafts I own that still have open review comments', function () {
        $mine = queueVersion(VersionStatus::Draft, ['document_number' => 'MINE', 'owner' => $this->legal]);
        $resolved = queueVersion(VersionStatus::Draft, ['document_number' => 'RESOLVED', 'owner' => $this->legal]);
        $others = queueVersion(VersionStatus::Draft, ['document_number' => 'OTHERS']);
        foreach ([[$mine, 'open'], [$resolved, 'resolved'], [$others, 'open']] as [$version, $status]) {
            ReviewComment::query()->forceCreate(['tenant_id' => $this->tenant->id, 'version_id' => $version->id, 'author_user_id' => $this->admin->id, 'author_name' => 'A', 'anchor' => [], 'body' => 'x', 'status' => $status, 'review_round' => 1]);
        }

        $props = queuePage($this->legal, ['kind' => 'revise']);

        expect(queueNumbers($props))->toBe(['MINE'])->and($props['items']['data'][0]['detail'])->toStartWith('1 komentar belum selesai');
    });

    it('lists approved versions only for people who can release', function () {
        queueVersion(VersionStatus::Approved, ['document_number' => 'READY'], ['scheduled_effective_at' => now()->addDays(3)]);
        queueVersion(VersionStatus::Approved, ['document_number' => 'READY-2']);
        queueVersion(VersionStatus::Draft, ['document_number' => 'DRAFT']);

        $props = queuePage($this->admin, ['kind' => 'release']);

        expect(queueNumbers($props))->toEqualCanonicalizing(['READY', 'READY-2']);
        expect(collect($props['items']['data'])->pluck('detail')->join('|'))->toContain('Dijadwalkan berlaku')->toContain('Belum dijadwalkan');
        expect(queueNumbers(queuePage($this->viewer, ['kind' => 'release'])))->toBe([]);
    });

    it('lists distributed versions I have not acknowledged', function () {
        $unread = queueVersion(VersionStatus::Effective, ['document_number' => 'UNREAD']);
        $read = queueVersion(VersionStatus::Effective, ['document_number' => 'READ']);
        $done = queueVersion(VersionStatus::Effective, ['document_number' => 'DONE']);
        $others = queueVersion(VersionStatus::Effective, ['document_number' => 'OTHERS']);
        foreach ([[$unread, $this->viewer, ReceiptStatus::Pending], [$read, $this->viewer, ReceiptStatus::Read], [$done, $this->viewer, ReceiptStatus::Acknowledged], [$others, $this->legal, ReceiptStatus::Pending]] as [$version, $user, $status]) {
            ReadReceipt::query()->forceCreate(['tenant_id' => $this->tenant->id, 'version_id' => $version->id, 'user_id' => $user->id, 'status' => $status]);
        }

        expect(queueNumbers(queuePage($this->viewer, ['kind' => 'read'])))->toEqualCanonicalizing(['UNREAD', 'READ']);
    });

    it('lists my documents in force whose review date is within thirty days or past', function () {
        queueVersion(VersionStatus::Effective, ['document_number' => 'LATE'], ['review_due_at' => now()->subDays(5)]);
        queueVersion(VersionStatus::Effective, ['document_number' => 'SOON'], ['review_due_at' => now()->addDays(20)]);
        queueVersion(VersionStatus::Effective, ['document_number' => 'FAR'], ['review_due_at' => now()->addDays(90)]);
        queueVersion(VersionStatus::Effective, ['document_number' => 'NONE']);
        queueVersion(VersionStatus::Effective, ['document_number' => 'NOT-MINE', 'owner' => $this->legal], ['review_due_at' => now()->addDays(5)]);

        $props = queuePage($this->admin, ['kind' => 'review']);

        expect(queueNumbers($props))->toEqualCanonicalizing(['LATE', 'SOON']);
        expect(collect($props['items']['data'])->firstWhere('document.number', 'LATE'))->toMatchArray(['urgent' => true])
            ->and(collect($props['items']['data'])->firstWhere('document.number', 'LATE')['detail'])->toStartWith('Terlambat sejak');
    });
});

describe('page', function () {
    it('shows a tab for every kind with its count, and opens the first one with work when none is chosen', function () {
        queueVersion(VersionStatus::Approved);
        queueVersion(VersionStatus::Approved);

        $props = queuePage($this->admin);

        expect(collect($props['kinds'])->pluck('count', 'value')->all())->toBe(['sign' => 0, 'revise' => 0, 'release' => 2, 'read' => 0, 'review' => 0]);
        expect($props['kind'])->toMatchArray(['value' => 'release', 'label' => 'Siap diberlakukan', 'action' => 'Berlakukan']);
        expect(queuePage($this->admin, ['kind' => 'nonsense'])['kind']['value'])->toBe('release');
        expect(queuePage($this->viewer)['kind']['value'])->toBe('sign');
    });

    it('does not leak other companies', function () {
        $other = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
        $foreign = Document::factory()->for($other->tenant)->create(['owner_user_id' => $other->id, 'created_by_id' => $other->id]);
        DocumentVersion::factory()->for($foreign)->create(['status' => VersionStatus::Approved, 'created_by_id' => $other->id]);
        queueVersion(VersionStatus::Approved, ['document_number' => 'MINE']);

        expect(queueNumbers(queuePage($this->admin, ['kind' => 'release'])))->toBe(['MINE']);
    });

    it('refuses a super admin, who has no company and so no tasks', function () {
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('documents.queue'))->assertForbidden();
    });

    it('redirects guests to the login page', function () {
        $this->get(route('documents.queue'))->assertRedirect(route('login'));
    });

    it('searches, filters, sorts oldest first by default and pages', function () {
        $sop = DocumentType::factory()->for($this->tenant)->create(['code' => 'SOP']);
        $ik = DocumentType::factory()->for($this->tenant)->create(['code' => 'IK']);
        $hrd = Department::factory()->for($this->tenant)->create(['code' => 'HRD']);
        $this->travelTo(now()->subDays(10));
        queueVersion(VersionStatus::Approved, ['document_number' => 'OLD', 'title' => 'Pengadaan barang', 'doc_type_id' => $sop->id, 'department_id' => $hrd->id]);
        $this->travelBack();
        queueVersion(VersionStatus::Approved, ['document_number' => 'NEW', 'title' => 'Cuti tahunan', 'doc_type_id' => $ik->id]);

        expect(queueNumbers(queuePage($this->admin, ['kind' => 'release'])))->toBe(['OLD', 'NEW']);
        expect(queueNumbers(queuePage($this->admin, ['kind' => 'release', 'direction' => 'desc'])))->toBe(['NEW', 'OLD']);
        expect(queueNumbers(queuePage($this->admin, ['kind' => 'release', 'search' => 'cuti'])))->toBe(['NEW']);
        expect(queueNumbers(queuePage($this->admin, ['kind' => 'release', 'filter' => ['type' => ['SOP']]])))->toBe(['OLD']);
        expect(queueNumbers(queuePage($this->admin, ['kind' => 'release', 'filter' => ['department' => ['HRD']]])))->toBe(['OLD']);
        expect(queueNumbers(queuePage($this->admin, ['kind' => 'release', 'filter' => ['waiting' => '7']])))->toBe(['OLD']);
        expect(queuePage($this->admin, ['kind' => 'release', 'per_page' => 10])['list']['sort'])->toBe('waiting');
    });

    it('carries the item shape the screen needs without internal ids', function () {
        queueVersion(VersionStatus::Approved, ['document_number' => 'ONE', 'title' => 'Satu']);

        $item = queuePage($this->admin, ['kind' => 'release'])['items']['data'][0];

        expect(array_keys($item))->toBe(['id', 'document', 'major', 'minor', 'status', 'status_label', 'waiting_since', 'detail', 'urgent'])
            ->and(array_keys($item['document']))->toBe(['id', 'number', 'title', 'type', 'department', 'owner']);
    });
});
