<?php

use App\Enums\AiTools\ChangeKind;
use App\Enums\AiTools\ComparisonStatus;
use App\Enums\AiTools\Pairing;
use App\Enums\SystemRole;
use App\Jobs\AiTools\RunComparisonStage;
use App\Models\AiTools\DocumentComparison;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\TextPdf;

beforeEach(function () {
    Storage::fake('comparison');
    config(['ai.comparison.disk' => 'comparison']);
    $this->tenant = Tenant::factory()->create();
    $this->legal = User::factory()->for($this->tenant)->role(SystemRole::Legal)->create();
});

function pdfFile(string $name = 'a.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, TextPdf::make(['Pasal 1 Isi dokumen yang cukup panjang untuk dibandingkan.']));
}

function archiveVersion(Tenant $tenant, string $title = 'Prosedur Cuti'): DocumentVersion
{
    $owner = User::factory()->for($tenant)->role(SystemRole::Admin)->create();
    $document = Document::factory()->for($tenant)->create(['owner_user_id' => $owner->id, 'created_by_id' => $owner->id, 'title' => $title]);

    return DocumentVersion::factory()->for($document)->create(['created_by_id' => $owner->id]);
}

describe('access', function () {
    it('sends guests to login and refuses people without the permission or a company', function () {
        $this->get(route('ai-tools.comparisons.create'))->assertRedirect(route('login'));

        foreach ([User::factory()->for($this->tenant)->role(SystemRole::Marketing)->create(), User::factory()->superAdmin()->create()] as $user) {
            $this->actingAs($user)->get(route('ai-tools.comparisons.index'))->assertForbidden();
            $this->actingAs($user)->get(route('ai-tools.comparisons.create'))->assertForbidden();
            $this->actingAs($user)->post(route('ai-tools.comparisons.store'), ['a' => ['kind' => 'paste', 'text' => 'x'], 'b' => ['kind' => 'paste', 'text' => 'y']])->assertForbidden();
        }
    });

    it('shows the menu entry only to people who can use it', function () {
        $titles = fn (User $user) => collect($this->actingAs($user)->get(route('dashboard'))->inertiaProps('navigation'))
            ->flatMap(fn ($module) => collect($module['items'])->pluck('title'));

        expect($titles($this->legal))->toContain('Bandingkan 2 Dokumen');
    });
});

describe('store', function () {
    it('creates the comparison from an upload and pasted text and queues the pipeline', function () {
        Bus::fake();

        $response = $this->actingAs($this->legal)->post(route('ai-tools.comparisons.store'), [
            'a' => ['kind' => 'upload', 'file' => pdfFile()],
            'b' => ['kind' => 'paste', 'label' => 'Draf vendor', 'text' => "Pasal 1\nIsi dokumen B."],
        ]);

        $comparison = DocumentComparison::query()->firstOrFail();

        $response->assertRedirect(route('ai-tools.comparisons.show', $comparison));
        expect($comparison->tenant_id)->toBe($this->tenant->id)
            ->and($comparison->created_by_id)->toBe($this->legal->id)
            ->and($comparison->status)->toBe(ComparisonStatus::Queued)
            ->and($comparison->label_a)->toBe('a')
            ->and($comparison->label_b)->toBe('Draf vendor')
            ->and($comparison->sources)->toHaveCount(2);

        $upload = $comparison->source('a');
        Storage::disk('comparison')->assertExists($upload->file_path);
        expect($upload->sha256)->toHaveLength(64);

        Bus::assertChained(array_fill(0, 6, RunComparisonStage::class));
    });

    it('takes a document from the archive of the same company only', function () {
        Bus::fake();
        $own = archiveVersion($this->tenant);
        $foreign = archiveVersion(Tenant::factory()->create(), 'Rahasia Perusahaan Lain');

        $this->actingAs($this->legal)->post(route('ai-tools.comparisons.store'), ['a' => ['kind' => 'archive', 'version' => $foreign->uuid], 'b' => ['kind' => 'paste', 'text' => 'abc']])
            ->assertSessionHasErrors('a.version');
        expect(DocumentComparison::count())->toBe(0);

        $this->actingAs($this->legal)->post(route('ai-tools.comparisons.store'), ['a' => ['kind' => 'archive', 'version' => $own->uuid], 'b' => ['kind' => 'paste', 'text' => 'abc']])
            ->assertRedirect();

        expect(DocumentComparison::firstOrFail()->source('a')->dcs_version_id)->toBe($own->id);
    });

    it('validates the sources', function () {
        Bus::fake();
        $version = archiveVersion($this->tenant);
        config(['ai.comparison.max_file_kb' => 1]);

        $post = fn (array $a, array $b) => $this->actingAs($this->legal)->post(route('ai-tools.comparisons.store'), ['a' => $a, 'b' => $b]);

        $post([], [])->assertSessionHasErrors(['a.kind', 'b.kind']);
        $post(['kind' => 'upload'], ['kind' => 'paste'])->assertSessionHasErrors(['a.file', 'b.text']);
        $post(['kind' => 'upload', 'file' => UploadedFile::fake()->createWithContent('x.pdf', 'bukan pdf sama sekali')], ['kind' => 'paste', 'text' => 'x'])->assertSessionHasErrors('a.file');
        $post(['kind' => 'upload', 'file' => UploadedFile::fake()->createWithContent('x.docx', '%PDF-1.4')], ['kind' => 'paste', 'text' => 'x'])->assertSessionHasErrors('a.file');
        $post(['kind' => 'upload', 'file' => UploadedFile::fake()->createWithContent('big.pdf', '%PDF-1.4'.str_repeat('x', 3000))], ['kind' => 'paste', 'text' => 'x'])->assertSessionHasErrors('a.file');
        $post(['kind' => 'archive', 'version' => $version->uuid], ['kind' => 'archive', 'version' => $version->uuid])->assertSessionHasErrors('b.version');

        expect(DocumentComparison::count())->toBe(0);
        Bus::assertNothingDispatched();
    });
});

describe('show, index and destroy', function () {
    beforeEach(function () {
        $this->comparison = DocumentComparison::factory()->create(['tenant_id' => $this->tenant->id, 'created_by_id' => $this->legal->id, 'status' => ComparisonStatus::Completed, 'progress' => 100, 'summary' => ['pokok_perubahan' => 'Ada perubahan.']]);
    });

    it('shows the matrix to its owner only', function () {
        $a = $this->comparison->articles()->create(['side' => 'a', 'ordinal' => 1, 'number_label' => 'Pasal 1', 'body' => 'teks lama']);
        $b = $this->comparison->articles()->create(['side' => 'b', 'ordinal' => 1, 'number_label' => 'Pasal 1', 'body' => 'teks baru']);
        $this->comparison->rows()->create(['ordinal' => 1, 'article_a_id' => $a->id, 'article_b_id' => $b->id, 'pairing' => Pairing::Number, 'change_kind' => ChangeKind::Changed, 'explanation' => 'Redaksi berubah.', 'diff' => ['a' => [['t' => 'teks lama', 'o' => 'del']], 'b' => [['t' => 'teks baru', 'o' => 'ins']]]]);

        $this->actingAs($this->legal)->get(route('ai-tools.comparisons.show', $this->comparison))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('ai-tools/comparisons/Show')
            ->where('comparison.status.value', 'completed')
            ->where('comparison.rows.0.kind.label', 'Diubah')
            ->where('comparison.rows.0.diff.b.0.o', 'ins')
            ->where('comparison.summary.pokok_perubahan', 'Ada perubahan.')
            ->missing('comparison.id_internal'));

        $colleague = User::factory()->for($this->tenant)->role(SystemRole::Legal)->create();
        $outsider = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Legal)->create();

        $this->actingAs($colleague)->get(route('ai-tools.comparisons.show', $this->comparison))->assertNotFound();
        $this->actingAs($outsider)->get(route('ai-tools.comparisons.show', $this->comparison))->assertNotFound();
    });

    it('hides the matrix and shows progress while it is still running', function () {
        $this->comparison->forceFill(['status' => ComparisonStatus::Processing, 'progress' => 40, 'summary' => null])->save();

        $this->actingAs($this->legal)->get(route('ai-tools.comparisons.show', $this->comparison))->assertInertia(fn (Assert $page) => $page
            ->where('comparison.progress', 40)->where('comparison.rows', [])->where('comparison.summary', null));
    });

    it('lists only my own comparisons', function () {
        DocumentComparison::factory()->create(['tenant_id' => $this->tenant->id, 'created_by_id' => User::factory()->for($this->tenant)->role(SystemRole::Legal)->create()->id]);
        DocumentComparison::factory()->create();

        $this->actingAs($this->legal)->get(route('ai-tools.comparisons.index'))->assertInertia(fn (Assert $page) => $page
            ->has('comparisons.data', 1)->where('comparisons.data.0.id', $this->comparison->uuid));
    });

    it('deletes a comparison with its files, for its owner only', function () {
        Storage::disk('comparison')->put('x/a.pdf', 'pdf');
        $this->comparison->sources()->create(['side' => 'a', 'kind' => 'upload', 'file_path' => 'x/a.pdf']);
        $colleague = User::factory()->for($this->tenant)->role(SystemRole::Legal)->create();

        $this->actingAs($colleague)->delete(route('ai-tools.comparisons.destroy', $this->comparison))->assertNotFound();
        $this->actingAs($this->legal)->delete(route('ai-tools.comparisons.destroy', $this->comparison))->assertRedirect(route('ai-tools.comparisons.index'));

        expect(DocumentComparison::count())->toBe(0);
        Storage::disk('comparison')->assertMissing('x/a.pdf');
    });

    it('prunes comparisons older than the retention period', function () {
        $old = DocumentComparison::factory()->create(['tenant_id' => $this->tenant->id, 'created_by_id' => $this->legal->id, 'created_at' => now()->subDays(91)]);

        $this->artisan('comparisons:prune')->assertSuccessful();

        expect(DocumentComparison::find($old->id))->toBeNull()->and(DocumentComparison::find($this->comparison->id))->not->toBeNull();
    });
});

describe('archive search', function () {
    it('finds versions of the own company', function () {
        archiveVersion($this->tenant, 'Prosedur Cuti');
        archiveVersion(Tenant::factory()->create(), 'Prosedur Cuti Lain');

        $this->actingAs($this->legal)->getJson(route('ai-tools.comparisons.archive', ['q' => 'prosedur']))->assertOk()->assertJsonCount(1)->assertJsonPath('0.label', fn ($label) => str_contains($label, 'Prosedur Cuti'));
    });
});
