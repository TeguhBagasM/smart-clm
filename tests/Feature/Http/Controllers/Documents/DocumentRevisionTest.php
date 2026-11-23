<?php

use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app(SeedDcsTenantDefaults::class)->handle($this->tenant);
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
    $this->viewer = User::factory()->for($this->tenant)->role(SystemRole::Viewer)->create();
    $this->document = Document::factory()->for($this->tenant)->create(['owner_user_id' => $this->admin->id, 'created_by_id' => $this->admin->id]);
    $this->effective = DocumentVersion::factory()->for($this->document)->create(['major' => 1, 'minor' => 0, 'status' => VersionStatus::Effective]);
});

function revToast(): array
{
    return session()->get(SessionKey::FLASH_DATA)['toast'];
}

function revUrl(Document $document): string
{
    return route('documents.versions.store', $document);
}

describe('new revision', function () {
    it('is refused to guests, people without dcs.edit and other companies', function () {
        $this->post(revUrl($this->document), ['bump' => 'revisi'])->assertRedirect(route('login'));
        $this->actingAs($this->viewer)->post(revUrl($this->document), ['bump' => 'revisi'])->assertForbidden();

        $other = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
        $this->actingAs($other)->post(revUrl($this->document), ['bump' => 'revisi'])->assertNotFound();
        $this->actingAs(User::factory()->superAdmin()->create())->post(revUrl($this->document), ['bump' => 'revisi'])->assertNotFound();
        expect($this->document->versions()->count())->toBe(1);
    });

    it('validates the kind of change', function () {
        $this->actingAs($this->admin)->post(revUrl($this->document), ['bump' => 'lain'])->assertSessionHasErrors('bump');
        $this->actingAs($this->admin)->post(revUrl($this->document), [])->assertSessionHasErrors('bump');
        $this->actingAs($this->admin)->post(revUrl($this->document), ['bump' => 'revisi', 'change_summary' => str_repeat('x', 1001)])->assertSessionHasErrors('change_summary');
    });

    it('creates the next draft and opens it', function () {
        $response = $this->actingAs($this->admin)->post(revUrl($this->document), ['bump' => 'revisi', 'change_summary' => 'Perbaikan typo']);
        $draft = $this->document->versions()->where('status', VersionStatus::Draft->value)->sole();

        $response->assertRedirect(route('documents.show', ['document' => $this->document->uuid, 'version' => $draft->uuid]));
        expect([$draft->major, $draft->minor])->toBe([1, 1])->and($draft->change_summary)->toBe('Perbaikan typo')->and($draft->created_by_id)->toBe($this->admin->id)->and(revToast()['type'])->toBe('success');

        $this->document->versions()->whereKey($draft->id)->update(['status' => VersionStatus::Superseded->value]);
        $this->actingAs($this->admin)->post(revUrl($this->document), ['bump' => 'terbitan']);
        expect($this->document->versions()->orderByDesc('id')->first())->major->toBe(2)->minor->toBe(0);
    });

    it('offers the button only when no version is unfinished', function () {
        $can = fn (User $user) => $this->actingAs($user)->get(route('documents.show', $this->document))->viewData('page')['props']['document']['can']['create_version'];

        expect($can($this->admin))->toBeTrue()->and($can($this->viewer))->toBeFalse();

        DocumentVersion::factory()->for($this->document)->create(['major' => 1, 'minor' => 1, 'status' => VersionStatus::Draft]);
        expect($can($this->admin))->toBeFalse();
    });
});

describe('release number', function () {
    function revCreate(): Document
    {
        test()->actingAs(test()->admin)->post(route('documents.store'), ['docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Dibatalkan']);

        return Document::query()->where('tenant_id', test()->tenant->id)->latest('id')->firstOrFail();
    }

    it('is refused to guests, people without dcs.release and other companies', function () {
        $document = revCreate();
        auth()->logout();

        $this->post(route('documents.release-number', $document))->assertRedirect(route('login'));
        $this->actingAs($this->viewer)->post(route('documents.release-number', $document))->assertForbidden();
        $other = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
        $this->actingAs($other)->post(route('documents.release-number', $document))->assertNotFound();
        expect($document->fresh()->document_number)->not->toContain('nomor dilepas');
    });

    it('gives the number back and keeps the document', function () {
        $document = revCreate();
        $number = $document->document_number;

        $this->actingAs($this->admin)->post(route('documents.release-number', $document))->assertRedirect();

        expect(revToast()['type'])->toBe('success')->and($document->fresh()->document_number)->toContain('(nomor dilepas)')->and($document->fresh()->document_number)->not->toBe($number);
        $this->assertDatabaseHas('dcs_document_events', ['document_id' => $document->id, 'event_type' => 'number.released']);
    });

    it('explains why a number cannot be released and offers the button only when it can', function () {
        $document = revCreate();
        $can = fn () => $this->actingAs($this->admin)->get(route('documents.show', $document))->viewData('page')['props']['document']['can']['release_number'];

        expect($can())->toBeTrue();
        $document->versions()->update(['status' => VersionStatus::Approved->value]);
        expect($can())->toBeFalse();

        $this->actingAs($this->admin)->post(route('documents.release-number', $document))->assertRedirect();
        expect(revToast()['type'])->toBe('error')->and($document->fresh()->document_number)->not->toContain('nomor dilepas');
    });

    it('is not offered for a document whose number did not come from the counter', function () {
        $this->actingAs($this->admin)->get(route('documents.show', $this->document))->assertOk()->assertInertia(fn (Assert $page) => $page->where('document.can.release_number', false));
    });
});
