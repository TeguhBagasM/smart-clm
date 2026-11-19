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
use Illuminate\Support\Str;
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
    it('accepts the shipped default and refuses bad colours, an unknown placeholder, widths that do not add up or too many cells', function () {
        $rules = HeaderLayout::rules('headerLayout');
        $accepted = function (array $layout) use ($rules): bool {
            $validator = Validator::make(['headerLayout' => $layout], $rules);

            return $validator->passes() && HeaderLayout::problems($layout) === [];
        };

        expect($accepted(HeaderLayout::default()))->toBeTrue();

        $withCell = function (string $key, mixed $value, int $row = 2, int $cell = 0): array {
            $layout = HeaderLayout::default();
            $layout['rows'][$row]['cells'][$cell][$key] = $value;

            return $layout;
        };

        $crowded = HeaderLayout::default();
        $crowded['rows'][2]['cells'] = array_fill(0, 5, ['width' => 20, ...$crowded['rows'][2]['cells'][0]]);
        $border = HeaderLayout::default();
        $border['border'] = 'url(javascript:alert(1))';

        expect($accepted($withCell('background', '#12345')))->toBeFalse()
            ->and($accepted($withCell('background', 'red')))->toBeFalse()
            ->and($accepted($withCell('background', 'expression(1)')))->toBeFalse()
            ->and($accepted($withCell('color', '#GGGGGG')))->toBeFalse()
            ->and($accepted($withCell('background', '#1A2B3C80')))->toBeTrue()
            ->and($accepted($withCell('color', 'auto')))->toBeTrue()
            ->and($accepted($withCell('text', 'Rahasia {password}')))->toBeFalse()
            ->and($accepted($withCell('text', 'x { y')))->toBeFalse()
            ->and($accepted($withCell('width', 70)))->toBeFalse()
            ->and($accepted($crowded))->toBeFalse()
            ->and($accepted($border))->toBeFalse();
    });

    it('allows the status and date placeholders once each, at the end of a cell', function () {
        $layout = HeaderLayout::default();
        $layout['rows'][0]['cells'][0]['text'] = '{status} judul';

        expect(HeaderLayout::problems($layout))->toHaveKey('rows.0.cells.0.text');

        $twice = HeaderLayout::default();
        $twice['rows'][1]['cells'][0]['text'] = 'Tanggal {issuedDate}';

        expect(HeaderLayout::problems($twice))->toHaveKey('rows.2.cells.1.text');
    });

    it('keeps the logo inside the rows that exist, corrects the last cell to make 100 and strips tags', function () {
        $layout = HeaderLayout::default();
        $layout['rows'] = array_slice($layout['rows'], 0, 2);
        $layout['rows'][1]['cells'][0]['text'] = "<b>Judul</b>\x07 {title}";
        $layout['rows'][0]['cells'][0]['background'] = '#abcdef';
        $layout['rows'][0]['cells'] = [['width' => 33.333, ...$layout['rows'][0]['cells'][0]], ['width' => 33.333, ...$layout['rows'][0]['cells'][0]], ['width' => 33.333, ...$layout['rows'][0]['cells'][0]]];

        $normalized = HeaderLayout::normalize($layout);

        expect($normalized['logo']['rows'])->toBe(2)
            ->and(array_sum(array_column($normalized['rows'][0]['cells'], 'width')))->toBe(100.0)
            ->and($normalized['rows'][0]['cells'][0]['background'])->toBe('#ABCDEF')
            ->and($normalized['rows'][1]['cells'][0]['text'])->toBe('Judul {title}');
    });

    it('converts a version 1 letterhead to the very colours the engine drew', function () {
        $v1 = [
            'logo' => ['enabled' => true, 'rows' => 2, 'width' => 22],
            'rows' => [
                ['height' => 20, 'cells' => [['span' => 12, 'label' => '', 'field' => 'docTypeName', 'bold' => true, 'italic' => false, 'uppercase' => true, 'align' => 'center', 'fill' => 'dark']]],
                ['height' => 18, 'cells' => [
                    ['span' => 7, 'label' => 'Nomor Dokumen', 'field' => 'documentNumber', 'bold' => false, 'italic' => false, 'uppercase' => false, 'align' => 'left', 'fill' => 'muted'],
                    ['span' => 5, 'label' => 'Catatan', 'field' => 'text', 'bold' => false, 'italic' => false, 'uppercase' => false, 'align' => 'left', 'fill' => 'none'],
                ]],
            ],
        ];

        $v2 = HeaderLayout::effective($v1);

        expect($v2['version'])->toBe(2)
            ->and($v2['rows'][0]['cells'][0])->toMatchArray(['text' => '{docTypeName}', 'background' => '#243661', 'color' => '#FFFFFF'])
            ->and($v2['rows'][1]['cells'][0])->toMatchArray(['text' => 'Nomor Dokumen : {documentNumber}', 'width' => 58.33, 'background' => '#EBEDF2', 'color' => '#1F212B'])
            ->and($v2['rows'][1]['cells'][1])->toMatchArray(['text' => 'Catatan', 'width' => 41.67, 'background' => null])
            ->and($v2['border'])->toBe('#404552')
            ->and(HeaderLayout::problems($v2))->toBe([]);
    });
});

describe('document type form', function () {
    it('saves a custom letterhead with free colours and offers it again, with the palette, when editing', function () {
        $layout = HeaderLayout::default();
        $layout['rows'][0]['cells'][0]['text'] = 'Dokumen Resmi';
        $layout['rows'][0]['cells'][0]['background'] = '#ab12cd';
        $layout['rows'][5]['cells'][1]['text'] = 'Pemilik : {owner}';

        $this->actingAs($this->admin)->post(route('documents.config.types.store'), [...hdrType(), 'headerLayout' => $layout])->assertSessionHasNoErrors();
        $type = $this->tenant->dcsDocumentTypes()->where('code', 'PRG')->first();
        expect($type->header_layout['rows'][0]['cells'][0]['text'])->toBe('Dokumen Resmi')
            ->and($type->header_layout['rows'][0]['cells'][0]['background'])->toBe('#AB12CD');

        $this->actingAs($this->admin)->get(route('documents.config.types.edit', $type->uuid))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('type.headerLayout.rows.0.cells.0.background', '#AB12CD')
            ->where('type.headerLayout.rows.5.cells.1.text', 'Pemilik : {owner}')
            ->has('header_fields', count(HeaderField::cases()))
            ->where('header_default.logo.rows', 4)
            ->has('header_palette.swatches', 16));
    });

    it('shows the default letterhead for a type that never customised it, and rejects a bad one', function () {
        $sop = $this->tenant->dcsDocumentTypes()->where('code', 'SOP')->first();
        expect($sop->header_layout)->toBeNull();

        $this->actingAs($this->admin)->get(route('documents.config.types.edit', $sop->uuid))->assertInertia(fn (Assert $page) => $page->where('type.headerLayout.rows.2.cells.0.text', 'Nomor Dokumen : {documentNumber}'));

        $bad = HeaderLayout::default();
        $bad['rows'][0]['cells'][0]['background'] = 'neon';
        $this->actingAs($this->admin)->post(route('documents.config.types.store'), [...hdrType(), 'headerLayout' => $bad])->assertSessionHasErrors('headerLayout.rows.0.cells.0.background');

        $uneven = HeaderLayout::default();
        $uneven['rows'][2]['cells'][0]['width'] = 30;
        $this->actingAs($this->admin)->post(route('documents.config.types.store'), [...hdrType(), 'code' => 'UNE', 'headerLayout' => $uneven])->assertSessionHasErrors('headerLayout.rows.2.cells');
    });

    it('refuses a custom logo that is not an upload of this company', function () {
        $layout = HeaderLayout::default();
        $layout['logo']['source'] = 'custom';
        $layout['logo']['assetId'] = (string) Str::uuid();

        $this->actingAs($this->admin)->post(route('documents.config.types.store'), [...hdrType(), 'headerLayout' => $layout])->assertSessionHasErrors('headerLayout.logo.assetId');
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

describe('presets', function () {
    it('offers presets that all pass the same rules as a saved letterhead, with the default first', function () {
        $presets = HeaderLayout::presets();

        expect($presets[0]['key'])->toBe('iso')->and($presets[0]['layout'])->toBe(HeaderLayout::default());

        foreach ($presets as $preset) {
            $layout = $preset['layout'];
            expect(Validator::make(['headerLayout' => $layout], HeaderLayout::rules('headerLayout'))->passes())->toBeTrue($preset['key'])
                ->and(HeaderLayout::problems($layout))->toBe([])
                ->and(HeaderLayout::normalize($layout))->toBe($layout);
        }

        $this->actingAs($this->admin)->get(route('documents.config.types.create'))->assertInertia(fn (Assert $page) => $page->has('header_presets', count($presets))->where('header_presets.0.key', 'iso'));
    });
});

describe('legacy letterheads in the database', function () {
    it('are converted by the migration to the colours the engine drew, and the converted ones are left alone', function () {
        $v1 = ['logo' => ['enabled' => true, 'rows' => 1, 'width' => 22], 'rows' => [['height' => 20, 'cells' => [['span' => 12, 'label' => '', 'field' => 'docTypeName', 'bold' => true, 'italic' => false, 'uppercase' => true, 'align' => 'center', 'fill' => 'dark']]]]];
        $old = $this->tenant->dcsDocumentTypes()->where('code', 'SOP')->firstOrFail();
        $old->forceFill(['header_layout' => $v1])->save();
        $new = $this->tenant->dcsDocumentTypes()->where('code', 'IK')->firstOrFail();
        $new->forceFill(['header_layout' => HeaderLayout::default()])->save();

        (require database_path('migrations/2026_11_19_114228_convert_dcs_header_layouts_to_v2.php'))->up();

        expect($old->refresh()->header_layout['version'])->toBe(2)
            ->and($old->header_layout['rows'][0]['cells'][0])->toMatchArray(['text' => '{docTypeName}', 'background' => '#243661', 'color' => '#FFFFFF'])
            ->and($new->refresh()->header_layout)->toEqual(HeaderLayout::default());
    });
});
