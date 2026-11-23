<?php

use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\Rendition;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Ai\AiException;
use App\Support\Dcs\Ai\DcsAi;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;
use Tests\Support\FakeDcsAi;
use Tests\Support\FakePdfEngine;

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    $this->engine = new FakePdfEngine;
    $this->ai = new FakeDcsAi;
    app()->instance(PdfEngine::class, $this->engine);
    app()->instance(DcsAi::class, $this->ai);
    $this->tenant = Tenant::factory()->create();
    app(SeedDcsTenantDefaults::class)->handle($this->tenant);
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
    $this->viewer = User::factory()->for($this->tenant)->role(SystemRole::Viewer)->create();
    $this->other = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
    $this->actingAs($this->admin)->post(route('documents.store'), ['docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Prosedur Cuti']);
    $this->document = Document::query()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
    $this->version = $this->document->versions()->first();
    auth()->logout();
});

function extraUrl(string $name): string
{
    return route("documents.versions.{$name}", [test()->document, test()->version]);
}

function extraToast(): array
{
    return session()->get(SessionKey::FLASH_DATA)['toast'];
}

function versionCan(User $user, string $ability): bool
{
    return test()->actingAs($user)->get(route('documents.show', test()->document))->viewData('page')['props']['version']['can'][$ability];
}

describe('review schedule', function () {
    it('is refused to guests, people without dcs.edit and other companies', function () {
        $this->put(extraUrl('review-schedule'), ['review_due_at' => now()->addDay()->toDateString()])->assertRedirect(route('login'));
        $this->actingAs($this->viewer)->put(extraUrl('review-schedule'), ['review_due_at' => now()->addDay()->toDateString()])->assertForbidden();
        $this->actingAs($this->other)->put(extraUrl('review-schedule'), ['review_due_at' => now()->addDay()->toDateString()])->assertNotFound();
        expect($this->version->fresh()->review_due_at)->toBeNull();
    });

    it('validates the date and the reminder days', function () {
        $this->actingAs($this->admin)->put(extraUrl('review-schedule'), ['review_due_at' => 'bukan tanggal'])->assertSessionHasErrors('review_due_at');
        $this->actingAs($this->admin)->put(extraUrl('review-schedule'), ['review_due_at' => now()->subDay()->toDateString()])->assertSessionHasErrors('review_due_at');
        $this->actingAs($this->admin)->put(extraUrl('review-schedule'), ['reminder_days' => 0])->assertSessionHasErrors('reminder_days');
        $this->actingAs($this->admin)->put(extraUrl('review-schedule'), ['reminder_days' => 400])->assertSessionHasErrors('reminder_days');
    });

    it('saves, then clears the schedule, and records history', function () {
        $due = now()->addMonths(6)->toDateString();

        $this->actingAs($this->admin)->put(extraUrl('review-schedule'), ['review_due_at' => $due, 'reminder_days' => 14])->assertSessionHasNoErrors();
        $version = $this->version->fresh();
        expect($version->review_due_at->toDateString())->toBe($due)->and($version->review_reminder_days)->toBe(14)->and(extraToast()['type'])->toBe('success');
        $this->assertDatabaseHas('dcs_document_events', ['version_id' => $version->id, 'event_type' => 'review_schedule.set']);

        $this->actingAs($this->admin)->put(extraUrl('review-schedule'), ['review_due_at' => ''])->assertSessionHasNoErrors();
        expect($this->version->fresh()->review_due_at)->toBeNull()->and($this->version->fresh()->review_reminder_days)->toBe(30);
    });

    it('is offered to people who may edit', function () {
        expect(versionCan($this->admin, 'schedule_review'))->toBeTrue()->and(versionCan($this->viewer, 'schedule_review'))->toBeFalse();
    });
});

describe('translation', function () {
    it('is refused to guests, people without dcs.edit and other companies', function () {
        $this->post(extraUrl('translate'))->assertRedirect(route('login'));
        $this->actingAs($this->viewer)->post(extraUrl('translate'))->assertForbidden();
        $this->actingAs($this->other)->post(extraUrl('translate'))->assertNotFound();
        expect($this->ai->translated)->toBeEmpty();
    });

    it('stores an English reference copy', function () {
        $this->actingAs($this->admin)->post(extraUrl('translate'))->assertRedirect();

        expect(extraToast()['type'])->toBe('success')->and($this->ai->translated)->toHaveCount(1)
            ->and(Rendition::query()->where('kind', RenditionKind::Translated)->sole()->language)->toBe('en');
        $this->assertDatabaseHas('dcs_document_events', ['version_id' => $this->version->id, 'event_type' => 'translation.created']);
        $this->actingAs($this->admin)->get(route('documents.show', $this->document))->assertInertia(fn ($page) => $page->where('version.has_translation', true));
    });

    it('passes AI failures on as a toast and stores nothing', function () {
        $this->ai->fail = new AiException(503, 'Layanan AI Google sedang sibuk (bukan error sistem). Coba lagi beberapa saat lagi.');

        $this->actingAs($this->admin)->post(extraUrl('translate'))->assertRedirect();

        expect(extraToast())->toBe(['type' => 'error', 'message' => 'Layanan AI Google sedang sibuk (bukan error sistem). Coba lagi beberapa saat lagi.'])
            ->and(Rendition::query()->where('kind', RenditionKind::Translated)->count())->toBe(0);
    });

    it('is offered only for content written in the system', function () {
        expect(versionCan($this->admin, 'translate'))->toBeTrue()->and(versionCan($this->viewer, 'translate'))->toBeFalse();

        $uploaded = DocumentVersion::factory()->for($this->document)->create(['major' => 2, 'minor' => 0, 'status' => VersionStatus::Draft]);
        $page = $this->actingAs($this->admin)->get(route('documents.show', [$this->document, 'version' => $uploaded->uuid]))->viewData('page');

        expect($page['props']['version']['can']['translate'])->toBeFalse();
        $this->actingAs($this->admin)->post(route('documents.versions.translate', [$this->document, $uploaded]))->assertRedirect();
        expect(extraToast()['type'])->toBe('error');
    });
});
