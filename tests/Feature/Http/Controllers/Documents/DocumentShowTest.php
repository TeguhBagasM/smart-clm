<?php

use App\Actions\Dcs\StoreRendition;
use App\Enums\Dcs\ActorType;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\ReceiptStatus;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Approval;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ReadReceipt;
use App\Models\Dcs\ReviewComment;
use App\Models\Dcs\VersionStep;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePdfEngine;

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    app()->instance(PdfEngine::class, new FakePdfEngine);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
    $this->legal = User::factory()->for($this->tenant)->role(SystemRole::Legal)->create();
    $this->document = Document::factory()->for($this->tenant)->create(['owner_user_id' => $this->admin->id, 'created_by_id' => $this->admin->id, 'title' => 'Prosedur Cuti']);
    $this->v1 = DocumentVersion::factory()->for($this->document)->create(['major' => 1, 'minor' => 0, 'status' => VersionStatus::Effective, 'created_by_id' => $this->admin->id, 'effective_at' => '2026-01-01 00:00:00']);
    $this->v2 = DocumentVersion::factory()->for($this->document)->create(['major' => 2, 'minor' => 0, 'status' => VersionStatus::UnderReview, 'created_by_id' => $this->admin->id, 'change_summary' => 'Revisi besar']);
    $this->document->forceFill(['current_version_id' => $this->v1->id])->save();
});

function addEvent(Document $document, DocumentEventType $type, array $attributes = []): DocumentEvent
{
    return DocumentEvent::query()->forceCreate([
        'occurred_at' => now(), 'tenant_id' => $document->tenant_id, 'document_id' => $document->id, 'event_type' => $type,
        'actor_type' => ActorType::User, 'actor_name' => 'Rina', 'source' => 'live', ...$attributes,
    ]);
}

describe('show', function () {
    it('redirects guests and hides documents of other companies', function () {
        $this->get(route('documents.show', $this->document))->assertRedirect(route('login'));
        $foreign = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();

        $this->actingAs($foreign)->get(route('documents.show', $this->document))->assertNotFound();
        $this->actingAs($foreign)->get(route('documents.timeline', $this->document))->assertNotFound();
        $this->actingAs($foreign)->get(route('documents.versions.file', [$this->document, $this->v1]))->assertNotFound();
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('documents.show', $this->document))->assertOk();
    });

    it('shows the header and the versions, newest first, with the version in force selected', function () {
        app(StoreRendition::class)->handle($this->admin, $this->v1, RenditionKind::Composed, FakePdfEngine::pdf());

        $this->actingAs($this->legal)->get(route('documents.show', $this->document))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('documents/Show')
            ->where('document.id', $this->document->uuid)->where('document.title', 'Prosedur Cuti')->where('document.owner', $this->admin->name)
            ->missing('document.owner_user_id')
            ->where('document.versions.0', fn ($v) => $v['id'] === $this->v2->uuid && $v['is_latest'] && ! $v['is_current'] && ! $v['has_file'] && $v['status_label'] === 'Menunggu tanda tangan' && $v['change_summary'] === 'Revisi besar')
            ->where('document.versions.1', fn ($v) => $v['id'] === $this->v1->uuid && $v['is_current'] && $v['has_file'])
            ->where('version.id', $this->v1->uuid)->where('version.has_file', true));
    });

    it('picks the version from the query, falls back to the newest without one in force, and ignores unknown ones', function () {
        $get = fn (array $query = []) => $this->actingAs($this->admin)->get(route('documents.show', [$this->document, ...$query]));

        $get(['version' => $this->v2->uuid])->assertInertia(fn (Assert $page) => $page->where('version.id', $this->v2->uuid));
        $get(['version' => 'not-a-version'])->assertInertia(fn (Assert $page) => $page->where('version.id', $this->v1->uuid));
        $this->document->forceFill(['current_version_id' => null])->save();
        $get()->assertInertia(fn (Assert $page) => $page->where('version.id', $this->v2->uuid));
        $get(['version' => (string) Str::uuid()])->assertInertia(fn (Assert $page) => $page->where('version.id', $this->v2->uuid));
    });

    it('reloads only the version when switching versions', function () {
        $this->actingAs($this->admin)->get(route('documents.show', $this->document))->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('version', fn (Assert $reloaded) => $reloaded->where('version.id', $this->v1->uuid)));
    });

    it('shows the signing sheet with who signed, who is waiting and who is next', function () {
        foreach ([['Dibuat oleh', $this->legal], ['Disetujui oleh', $this->admin]] as $position => [$label, $assigned]) {
            VersionStep::query()->forceCreate(['version_id' => $this->v2->id, 'group_position' => 1, 'position' => $position + 1, 'label' => $label, 'role_name' => $position === 0 ? 'Legal' : null, 'assigned_user_id' => $assigned->id, 'status' => 'pending']);
        }
        VersionStep::query()->forceCreate(['version_id' => $this->v2->id, 'group_position' => 2, 'position' => 1, 'label' => 'Auditor', 'status' => 'pending']);
        $rendition = app(StoreRendition::class)->handle($this->admin, $this->v2, RenditionKind::Signed, FakePdfEngine::pdf());
        Approval::query()->forceCreate(['version_id' => $this->v2->id, 'step_order' => 1, 'column_label' => 'Dibuat oleh', 'approver_user_id' => $this->legal->id, 'approver_role_key' => 'legal', 'signed_content_sha256' => $rendition->sha256, 'rendition_id' => $rendition->id, 'review_round' => 1, 'decided_at' => '2026-02-20 10:00:00']);

        $this->actingAs($this->admin)->get(route('documents.show', [$this->document, 'version' => $this->v2->uuid]))->assertInertia(fn (Assert $page) => $page
            ->where('version.roster', fn ($roster) => collect($roster)->map(fn ($e) => [$e['label'], $e['status'], $e['is_current'], $e['signed_by'], $e['assigned_to']])->all() === [
                ['Dibuat oleh', 'approved', false, $this->legal->name, $this->legal->name],
                ['Disetujui oleh', 'pending', true, null, $this->admin->name],
                ['Auditor', 'pending', false, null, null],
            ]));
    });

    it('does not mark anyone as current unless the version is under review', function () {
        VersionStep::query()->forceCreate(['version_id' => $this->v1->id, 'group_position' => 1, 'position' => 1, 'label' => 'Disetujui oleh', 'status' => 'pending']);

        $this->actingAs($this->admin)->get(route('documents.show', $this->document))->assertInertia(fn (Assert $page) => $page
            ->where('version.roster.0.is_current', false));
    });

    it('shows review comments, read receipts and the outside review state', function () {
        ReviewComment::query()->forceCreate(['tenant_id' => $this->tenant->id, 'version_id' => $this->v1->id, 'author_user_id' => null, 'author_name' => 'Auditor Budi', 'author_role_key' => 'Pihak Eksternal', 'anchor' => ['field' => 'section', 'sectionHeading' => 'Bab 1'], 'body' => 'Kurang jelas', 'status' => 'open', 'review_round' => 1]);
        ReadReceipt::query()->forceCreate(['tenant_id' => $this->tenant->id, 'version_id' => $this->v1->id, 'user_id' => $this->legal->id, 'status' => ReceiptStatus::Acknowledged, 'read_at' => now(), 'acknowledged_at' => now()]);
        ReadReceipt::query()->forceCreate(['tenant_id' => $this->tenant->id, 'version_id' => $this->v1->id, 'user_id' => $this->admin->id, 'status' => ReceiptStatus::Pending]);

        $this->actingAs($this->admin)->get(route('documents.show', $this->document))->assertInertia(fn (Assert $page) => $page
            ->where('version.comments.0', fn ($c) => $c['author'] === 'Auditor Budi' && $c['external'] === true && $c['status'] === 'open' && $c['anchor']['sectionHeading'] === 'Bab 1')
            ->where('version.receipts.counts', ['pending' => 1, 'read' => 0, 'acknowledged' => 1])
            ->has('version.receipts.recipients', 2)->where('version.external_review', null));
    });

    it('lists the six newest history entries', function () {
        foreach (range(1, 8) as $n) {
            addEvent($this->document, DocumentEventType::CommentAdded, ['occurred_at' => now()->subHours(10 - $n), 'actor_name' => "Orang {$n}"]);
        }

        $this->actingAs($this->admin)->get(route('documents.show', $this->document))->assertInertia(fn (Assert $page) => $page
            ->has('recent_events', 6)->where('recent_events.0.actor', 'Orang 8')->where('recent_events.0.label', 'Komentar ditambahkan'));
    });
});

describe('timeline', function () {
    beforeEach(function () {
        addEvent($this->document, DocumentEventType::DocumentCreated, ['occurred_at' => '2026-01-01 08:00:00', 'actor_name' => 'Rina']);
        addEvent($this->document, DocumentEventType::Signed, ['occurred_at' => '2026-02-01 08:00:00', 'actor_name' => 'Budi', 'version_id' => $this->v2->id, 'payload' => ['column' => 'Dibuat oleh', 'rendition_id' => 7]]);
        addEvent($this->document, DocumentEventType::ExternalReviewApproved, ['occurred_at' => '2026-03-01 08:00:00', 'actor_type' => ActorType::Guest, 'actor_name' => 'Auditor', 'version_id' => $this->v2->id]);
        addEvent(Document::factory()->for($this->tenant)->create(), DocumentEventType::Released, ['actor_name' => 'Orang lain']);
    });

    function timelineLabels(array $query = []): array
    {
        $labels = [];
        test()->actingAs(test()->admin)->get(route('documents.timeline', [test()->document, ...$query]))->assertOk()
            ->assertInertia(function (Assert $page) use (&$labels) {
                $labels = collect($page->toArray()['props']['events']['data'])->pluck('label')->all();
            });

        return $labels;
    }

    it('lists only this document\'s history, newest first, with a summary and no internal ids', function () {
        $this->actingAs($this->admin)->get(route('documents.timeline', $this->document))->assertInertia(fn (Assert $page) => $page
            ->component('documents/Timeline')->has('events.data', 3)->where('events.data.0.label', 'Pihak eksternal menyetujui')
            ->where('events.data.1', fn ($e) => $e['summary'] === 'Kolom "Dibuat oleh"' && $e['version'] === ['major' => 2, 'minor' => 0] && $e['actor'] === 'Budi')
            ->missing('events.data.1.payload')->where('list.sort', 'occurred_at')->has('document.versions', 2));
    });

    it('searches by actor and filters by kind, version, kind of actor, name and date', function () {
        expect(timelineLabels(['search' => 'bud']))->toBe(['Ditandatangani']);
        expect(timelineLabels(['filter' => ['type' => ['version.signed', 'document.created']]]))->toBe(['Ditandatangani', 'Dokumen dibuat']);
        expect(timelineLabels(['filter' => ['version' => $this->v2->uuid]]))->toHaveCount(2);
        expect(timelineLabels(['filter' => ['actor_type' => 'guest']]))->toBe(['Pihak eksternal menyetujui']);
        expect(timelineLabels(['filter' => ['actor' => 'Rina']]))->toBe(['Dokumen dibuat']);
        expect(timelineLabels(['filter' => ['occurred' => ['from' => '2026-02-01', 'to' => '2026-02-28']]]))->toBe(['Ditandatangani']);
        expect(timelineLabels(['filter' => ['type' => ['nonsense'], 'occurred' => ['from' => 'x']]]))->toHaveCount(3);
    });

    it('sorts by time or kind and pages', function () {
        expect(timelineLabels(['sort' => 'occurred_at', 'direction' => 'asc']))->toBe(['Dokumen dibuat', 'Ditandatangani', 'Pihak eksternal menyetujui']);
        foreach (range(1, 12) as $n) {
            addEvent($this->document, DocumentEventType::CommentAdded, ['occurred_at' => now()]);
        }

        expect(timelineLabels(['per_page' => 10]))->toHaveCount(10);
        expect(timelineLabels(['per_page' => 10, 'page' => 2]))->toHaveCount(5);
    });

    it('offers the actors and versions of this document as filter options', function () {
        $this->actingAs($this->admin)->get(route('documents.timeline', $this->document))->assertInertia(fn (Assert $page) => $page
            ->where('list.filters', function ($filters) {
                $filters = collect($filters)->keyBy('key');

                return collect($filters['actor']['options'])->pluck('value')->sort()->values()->all() === ['Auditor', 'Budi', 'Rina']
                    && collect($filters['version']['options'])->pluck('label')->all() === ['Terbitan 2 Revisi 0', 'Terbitan 1 Revisi 0'];
            }));
    });
});

describe('file', function () {
    it('serves the watermarked PDF for the page and records a read receipt', function () {
        app(StoreRendition::class)->handle($this->admin, $this->v1, RenditionKind::Composed, FakePdfEngine::pdf());

        $response = $this->actingAs($this->legal)->get(route('documents.versions.file', [$this->document, $this->v1]))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-DCS-Copy-Status', 'effective');

        expect($response->getContent())->toStartWith('%PDF')->and($response->headers->get('Cache-Control'))->toContain('no-store');
        expect(ReadReceipt::query()->where('user_id', $this->legal->id)->sole()->status)->toBe(ReceiptStatus::Read);
        $this->actingAs($this->legal)->get(route('documents.versions.file', [$this->document, $this->v1, 'download' => 1]))->assertHeaderMissing('X-Nope')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.str_replace('/', '-', $this->document->document_number).'.pdf"');
    });

    it('answers 404 for a version of another document and 409 when there is no file', function () {
        $other = Document::factory()->for($this->tenant)->create();
        $foreignVersion = DocumentVersion::factory()->for($other)->create();

        $this->actingAs($this->admin)->get(route('documents.versions.file', [$this->document, $foreignVersion]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('documents.versions.file', [$this->document, $this->v2]))->assertStatus(409);
    });
});
