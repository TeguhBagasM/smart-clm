<?php

use App\Actions\MasterData\SeedTenantMasterData;
use App\Actions\Templates\SaveContractTemplate;
use App\Actions\Templates\SaveContractVariable;
use App\Actions\Templates\SeedTenantTemplateLibrary;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\TemplateClause;
use App\Models\Templates\TemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Templates\TemplatePreview;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->actor = User::factory()->for($this->tenant)->create();
    app(SeedTenantMasterData::class)->handle($this->tenant);
    app(SeedTenantTemplateLibrary::class)->handle($this->tenant);
});

it('fills a seeded template with sample values, never real ones', function () {
    $template = ContractTemplate::query()->where('tenant_id', $this->tenant->id)->where('legacy_key', 'tmp-2')->sole();
    $preview = TemplatePreview::for($template->draft()->sole());

    $fees = collect($preview['clauses'])->firstWhere('title', 'Nilai Kontrak & Pembayaran');

    expect($preview['missing'])->toBe([])
        ->and($preview['fields'])->toContain(['code' => 'ContractValue', 'name' => 'Nilai Kontrak', 'required' => true, 'sample' => '1.250.000'])
        ->and($fees['segments'][0])->toBe(['text' => 'Nilai Kontrak yang disepakati adalah sebesar '])
        ->and($fees['segments'][1])->toBe(['code' => 'ContractValue', 'value' => '1.250.000', 'missing' => false])
        ->and(collect($preview['fields'])->firstWhere('code', 'Currency')['sample'])->toBe('IDR');
});

it('fills the system tokens of a paragraph with their samples', function () {
    $draft = TemplateVersion::query()->where('contract_template_id', ContractTemplate::query()->where('legacy_key', 'tmp-3')->value('id'))->sole();
    $draft->forceFill(['opening_paragraph' => 'Antara {{Party1Name}} dan {{Party2Name}}.'])->save();

    $opening = TemplatePreview::for($draft->refresh())['opening'];

    expect($opening)->toBe([
        ['text' => 'Antara '], ['code' => 'Party1Name', 'value' => 'PT Contoh Perusahaan', 'missing' => false],
        ['text' => ' dan '], ['code' => 'Party2Name', 'value' => 'PT Contoh Mitra', 'missing' => false], ['text' => '.'],
    ]);
});

it('shows a placeholder that no field or token answers as missing, in brackets', function () {
    $draft = TemplateVersion::query()->where('contract_template_id', ContractTemplate::query()->where('legacy_key', 'tmp-1')->value('id'))->sole();
    TemplateClause::factory()->for($draft, 'version')->create(['position' => 9, 'title' => 'Tambahan', 'body' => 'Catatan {{Hantu}}.']);

    $preview = TemplatePreview::for($draft->refresh());
    $segments = collect($preview['clauses'])->firstWhere('title', 'Tambahan')['segments'];

    expect($preview['missing'])->toBe(['Hantu'])
        ->and($segments[1])->toBe(['code' => 'Hantu', 'value' => '[Hantu]', 'missing' => true]);
});

it('passes a default value through as data, unchanged, even when it looks like markup', function () {
    $variable = app(SaveContractVariable::class)->handle($this->actor, $this->tenant, ['code' => 'Catatan', 'name' => 'Catatan', 'value_type' => 'text', 'default_value' => '<img src=x onerror=alert(1)>']);
    $draft = TemplateVersion::query()->where('contract_template_id', app(SaveContractTemplate::class)->handle($this->actor, $this->tenant, ['name' => 'Coba', 'category_id' => ContractTemplate::query()->where('tenant_id', $this->tenant->id)->value('contract_category_id')])->id)->sole();
    $draft->variables()->attach($variable->id, ['is_required' => false, 'position' => 1]);

    expect(collect(TemplatePreview::for($draft->refresh())['fields'])->firstWhere('code', 'Catatan')['sample'])->toBe('<img src=x onerror=alert(1)>');
});
