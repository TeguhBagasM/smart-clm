<?php

use App\Actions\Dcs\ComposeVersion;
use App\Actions\Dcs\ViewVersion;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Pdf\PdfEngine;
use App\Support\Dcs\Pdf\PhpPdfEngine;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PdfParity;

/*
 * The whole path with the PHP engine and nothing else: the real actions, the real storage,
 * and no HTTP call at all (a call to the Node engine would fail the test).
 */

beforeEach(function () {
    if (! PdfParity::available()) {
        $this->markTestSkipped('poppler is needed to read the PDF back.');
    }

    Storage::fake('dcs');
    Http::preventStrayRequests();
    config(['dcs.disk' => 'dcs', 'dcs.numbering_timezone' => 'Asia/Jakarta']);

    $this->admin = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create(['name' => 'Dewi Ayu']);
    $this->tenant = $this->admin->tenant;
    $this->document = Document::factory()->for($this->tenant)->create(['owner_user_id' => $this->admin->id, 'created_by_id' => $this->admin->id]);
    $this->version = DocumentVersion::factory()->for($this->document)->create(['status' => VersionStatus::Draft, 'created_by_id' => $this->admin->id]);
});

it('binds the PHP engine, the only PDF engine there is', function () {
    expect(app(PdfEngine::class))->toBeInstanceOf(PhpPdfEngine::class);
});

it('composes a version into a stored PDF and records where the signature sheet is', function () {
    app(ComposeVersion::class)->handle($this->admin, $this->tenant, $this->version, [
        'purpose' => 'Menjelaskan prosedur.',
        'sections' => [['heading' => 'Langkah', 'content' => "Mengisi formulir.\nMenyerahkan formulir."]],
    ], null);

    $rendition = $this->version->fresh()->currentRendition();
    $pdf = Storage::disk('dcs')->get($rendition->storage_key);

    expect($pdf)->toStartWith('%PDF')
        ->and(hash('sha256', $pdf))->toBe($rendition->sha256)
        ->and(PdfParity::text($pdf))->toContain('Menjelaskan prosedur.')->toContain('Mengisi formulir.')
        ->and($this->version->content()->first()->render_layout)->toHaveKeys(['signatureSheetPageIndex', 'sigLayout', 'headerLayout']);
});

it('shows a composed version with the live watermark for the reader and leaves the stored file alone', function () {
    app(ComposeVersion::class)->handle($this->admin, $this->tenant, $this->version, ['purpose' => 'Isi dokumen.'], null);
    $this->version->forceFill(['status' => VersionStatus::Effective, 'effective_at' => now()])->save();
    $stored = Storage::disk('dcs')->get($this->version->fresh()->currentRendition()->storage_key);

    $viewed = app(ViewVersion::class)->handle($this->admin, $this->version->fresh(), false, '127.0.0.1', 'pest');

    expect(PdfParity::text($viewed['bytes']))->toContain('CONTROLLED COPY')->toContain('Accessed by Dewi Ayu')->toMatch('/Tanggal Efektif : \d{2}\/\d{2}\/\d{4}/')
        ->and(Storage::disk('dcs')->get($this->version->fresh()->currentRendition()->storage_key))->toBe($stored);
});
