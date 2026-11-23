<?php

use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\VersionAttachment;
use App\Models\Dcs\VersionSection;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Pdf\PdfEngine;
use Inertia\Support\SessionKey;
use Tests\Support\FakePdfEngine;

beforeEach(function () {
    $this->engine = new FakePdfEngine;
    app()->instance(PdfEngine::class, $this->engine);
    $this->tenant = Tenant::factory()->create();
    app(SeedDcsTenantDefaults::class)->handle($this->tenant);
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
    $this->actingAs($this->admin)->post(route('documents.store'), ['docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Prosedur Cuti']);
    $this->document = Document::query()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
    $this->version = $this->document->versions()->first();
    $this->version->sections()->delete();
    addSection(1, 'Bab 1', 'Isi bab');
});

function addSection(int $position, string $heading, string $content): void
{
    VersionSection::query()->forceCreate(['version_id' => test()->version->id, 'position' => $position, 'heading' => $heading, 'content' => $content]);
}

function composeNow()
{
    return test()->actingAs(test()->admin)->from(route('documents.versions.edit', [test()->document, test()->version]))->post(route('documents.versions.render', [test()->document, test()->version]));
}

function composeProblems($response): array
{
    $errors = session()->get('errors')?->getBag('default')->getMessages() ?? [];

    return collect($errors)->filter(fn ($messages, $key) => str_starts_with($key, 'compose.'))->flatten()->all();
}

it('composes a complete draft', function () {
    composeNow()->assertSessionHasNoErrors();

    expect(session()->get(SessionKey::FLASH_DATA)['toast']['type'])->toBe('success')->and($this->engine->composed)->toHaveCount(1);
});

it('lists what is wrong and where, and draws nothing', function () {
    addSection(2, 'Bab kosong', '');
    addSection(3, '', 'Tanpa judul');
    $this->version->content()->update(['flow_mode' => 'builder', 'flow_steps' => [
        ['id' => 'a', 'row' => 0, 'text' => 'Mulai', 'type' => 'start', 'actor' => 'Penyusun', 'order' => 1, 'nextId' => 'b'],
        ['id' => 'b', 'row' => 1, 'text' => null, 'type' => 'document', 'actor' => 'Penyusun', 'order' => 2],
    ]]);
    VersionAttachment::query()->forceCreate(['version_id' => $this->version->id, 'position' => 1, 'label' => '', 'url' => 'https://contoh.test/a.pdf']);

    composeNow()->assertSessionHasErrors();
    $problems = implode("\n", composeProblems(null));

    expect($problems)->not->toContain('Bab kosong')
        ->toContain('Isi dokumen, bagian 3: judul belum diisi')
        ->toContain('Diagram alir, langkah 2 (Dokumen): belum diberi teks')
        ->toContain('Lampiran 1: nama dan alamat lampiran harus diisi')
        ->and(session()->get(SessionKey::FLASH_DATA)['toast']['type'])->toBe('error')
        ->and($this->engine->composed)->toBeEmpty();
});

it('names the characters the PDF cannot print and where they are', function () {
    $this->version->sections()->update(['content' => 'Lanjut → ke langkah berikut ✓']);
    $this->version->content()->update(['purpose' => 'Tujuan 😀']);

    composeNow()->assertSessionHasErrors();
    $problems = implode("\n", composeProblems(null));

    expect($problems)->toContain('Isi dokumen, bagian 1: karakter "→", "✓" tidak bisa dicetak ke PDF')->toContain('Tujuan: karakter "😀" tidak bisa dicetak ke PDF')
        ->and($this->engine->composed)->toBeEmpty();
});

it('accepts Indonesian text, quotes, dashes and accents', function () {
    $this->version->sections()->update(['content' => "Prosedur “cuti” – tahunan • café\n  Baris kedua…"]);

    composeNow()->assertSessionHasNoErrors();

    expect($this->engine->composed)->toHaveCount(1);
});
