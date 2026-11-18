<?php

use App\Actions\Dcs\RenderVersion;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Header\HeaderField;
use App\Support\Dcs\Header\HeaderLayout;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePdfEngine;

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    $this->engine = new FakePdfEngine;
    app()->instance(PdfEngine::class, $this->engine);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
    $this->actingAs($this->admin)->get(route('documents.config.types.index'))->assertOk();
});

function hdrType(): array
{
    return ['code' => 'PRG', 'name' => 'Program', 'layoutStyle' => 'standard', 'headerStyle' => 'iso'];
}

describe('layout rules', function () {
    it('accepts the shipped default and refuses a cell that is too wide, an unknown field or too many cells', function () {
        $rules = HeaderLayout::rules('headerLayout');
        $passes = fn (array $layout): bool => Validator::make(['headerLayout' => $layout], $rules)->passes();

        expect($passes(HeaderLayout::default()))->toBeTrue();

        $wide = HeaderLayout::default();
        $wide['rows'][2]['cells'][0]['span'] = 13;
        $unknown = HeaderLayout::default();
        $unknown['rows'][2]['cells'][0]['field'] = 'password';
        $crowded = HeaderLayout::default();
        $crowded['rows'][2]['cells'] = array_fill(0, 5, $crowded['rows'][2]['cells'][0]);

        expect($passes($wide))->toBeFalse()->and($passes($unknown))->toBeFalse()->and($passes($crowded))->toBeFalse();
    });

    it('keeps the logo inside the rows that exist', function () {
        $layout = HeaderLayout::default();
        $layout['rows'] = array_slice($layout['rows'], 0, 2);

        expect(HeaderLayout::normalize($layout)['logo']['rows'])->toBe(2);
    });
});

describe('document type form', function () {
    it('saves a custom letterhead and offers it again, with the field catalog, when editing', function () {
        $layout = HeaderLayout::default();
        $layout['rows'][0]['cells'][0]['label'] = 'Dokumen Resmi';
        $layout['rows'][5]['cells'][1]['field'] = 'owner';

        $this->actingAs($this->admin)->post(route('documents.config.types.store'), [...hdrType(), 'headerLayout' => $layout])->assertSessionHasNoErrors();
        $type = $this->tenant->dcsDocumentTypes()->where('code', 'PRG')->first();
        expect($type->header_layout['rows'][0]['cells'][0]['label'])->toBe('Dokumen Resmi');

        $this->actingAs($this->admin)->get(route('documents.config.types.edit', $type->uuid))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('type.headerLayout.rows.0.cells.0.label', 'Dokumen Resmi')
            ->where('type.headerLayout.rows.5.cells.1.field', 'owner')
            ->has('header_fields', count(HeaderField::cases()))
            ->where('header_default.logo.rows', 4));
    });

    it('shows the default letterhead for a type that never customised it, and rejects a bad one', function () {
        $sop = $this->tenant->dcsDocumentTypes()->where('code', 'SOP')->first();
        expect($sop->header_layout)->toBeNull();

        $this->actingAs($this->admin)->get(route('documents.config.types.edit', $sop->uuid))->assertInertia(fn (Assert $page) => $page->where('type.headerLayout.rows.2.cells.0.field', 'documentNumber'));

        $bad = HeaderLayout::default();
        $bad['rows'][0]['cells'][0]['fill'] = 'neon';
        $this->actingAs($this->admin)->post(route('documents.config.types.store'), [...hdrType(), 'headerLayout' => $bad])->assertSessionHasErrors('headerLayout.rows.0.cells.0.fill');
    });
});

describe('composing', function () {
    it('sends the letterhead with values the system fills in, never what a person typed', function () {
        $sop = $this->tenant->dcsDocumentTypes()->where('code', 'SOP')->first();
        $sop->forceFill(['header_layout' => [...HeaderLayout::default(), 'rows' => array_slice(HeaderLayout::default()['rows'], 0, 3)]])->save();

        $this->actingAs($this->admin)->post(route('documents.store'), ['mode' => 'compose', 'docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Prosedur Cuti', 'initialMajor' => 9, 'initialMinor' => 9])->assertRedirect();
        $version = Document::query()->where('tenant_id', $this->tenant->id)->firstOrFail()->versions()->firstOrFail();
        app(RenderVersion::class)->handle($this->admin, $version);

        $header = $this->engine->composed[0]['header'];
        expect($header['layout']['rows'])->toHaveCount(3)
            ->and($header['values']['revisionLabel'])->toBe('00/'.now()->format('y'))
            ->and($header['values']['issueNumber'])->toBe('1')
            ->and($header['values']['title'])->toBe('Prosedur Cuti')
            ->and($header['values']['documentNumber'])->toStartWith('SOP/HRD/');
    });
});

describe('body style', function () {
    it('is saved on the type and sent to the engine, standard unless chosen', function () {
        $this->actingAs($this->admin)->post(route('documents.config.types.store'), [...hdrType(), 'bodyStyle' => 'formal'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('documents.config.types.store'), [...hdrType(), 'code' => 'PLAIN', 'name' => 'Biasa'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('documents.config.types.store'), [...hdrType(), 'code' => 'BAD', 'name' => 'X', 'bodyStyle' => 'neon'])->assertSessionHasErrors('bodyStyle');

        expect($this->tenant->dcsDocumentTypes()->where('code', 'PRG')->first()->body_style->value)->toBe('formal')
            ->and($this->tenant->dcsDocumentTypes()->where('code', 'PLAIN')->first()->body_style->value)->toBe('standard');

        $this->actingAs($this->admin)->post(route('documents.store'), ['mode' => 'compose', 'docTypeCode' => 'PRG', 'department' => 'HRD', 'title' => 'Formal'])->assertRedirect();
        app(RenderVersion::class)->handle($this->admin, Document::query()->where('tenant_id', $this->tenant->id)->firstOrFail()->versions()->firstOrFail());

        expect($this->engine->composed[0]['bodyStyle'])->toBe('formal');
    });
});
