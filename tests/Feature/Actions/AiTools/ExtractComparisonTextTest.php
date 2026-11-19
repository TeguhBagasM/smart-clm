<?php

use App\Actions\AiTools\ExtractComparisonText;
use App\Enums\AiTools\ExtractionMethod;
use App\Enums\AiTools\SourceKind;
use App\Models\AiTools\ComparisonSource;
use App\Models\AiTools\DocumentComparison;
use App\Support\Comparison\Extraction\ExtractionException;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeLlmClient;
use Tests\Support\TextPdf;

beforeEach(function () {
    Storage::fake('comparison');
    config(['ai.comparison.disk' => 'comparison']);
    $this->comparison = DocumentComparison::factory()->create();
});

function uploadSource(DocumentComparison $comparison, string $bytes, string $side = 'a'): ComparisonSource
{
    Storage::disk('comparison')->put("c/{$side}.pdf", $bytes);

    return $comparison->sources()->create(['side' => $side, 'kind' => SourceKind::Upload, 'file_path' => "c/{$side}.pdf", 'original_name' => "{$side}.pdf"]);
}

it('reads the text layer of a PDF without calling the model', function () {
    $fake = FakeLlmClient::install();
    $source = uploadSource($this->comparison, TextPdf::make([
        "Pasal 1\nPengusaha wajib membayar upah pekerja tepat waktu setiap bulan.",
        "Pasal 2\nPekerja berhak atas cuti tahunan selama dua belas hari kerja.",
    ]));

    $text = app(ExtractComparisonText::class)->handle($source);

    expect($text)->toContain('Pasal 1')->toContain('cuti tahunan')
        ->and($source->fresh()->extraction_method)->toBe(ExtractionMethod::TextLayer)
        ->and($source->fresh()->page_count)->toBe(2)
        ->and($fake->requests)->toBeEmpty();
});

it('sends only the pages without a text layer to OCR', function () {
    $fake = FakeLlmClient::install()->reply(fn () => ['pages' => [['page' => 1, 'text' => 'Pasal 2 hasil scan yang dibaca OCR dengan baik.']]]);
    $source = uploadSource($this->comparison, TextPdf::make([
        'Pasal 1 Pengusaha wajib membayar upah pekerja tepat waktu setiap bulan.',
        '',
    ]));

    $text = app(ExtractComparisonText::class)->handle($source);

    expect($text)->toContain('Pasal 1')->toContain('hasil scan')
        ->and($source->fresh()->extraction_method)->toBe(ExtractionMethod::Mixed)
        ->and($fake->requests)->toHaveCount(1)
        ->and($fake->requests[0]->files)->toHaveCount(1);
});

it('refuses a PDF over the page limit', function () {
    config(['ai.comparison.max_pages' => 1]);
    FakeLlmClient::install();
    $source = uploadSource($this->comparison, TextPdf::make(['Satu halaman cukup panjang untuk dibaca.', 'Dua halaman melebihi batas yang ditetapkan.']));

    app(ExtractComparisonText::class)->handle($source);
})->throws(ExtractionException::class, 'melebihi batas');

it('uses pasted text as is', function () {
    $source = $this->comparison->sources()->create(['side' => 'b', 'kind' => SourceKind::Paste, 'pasted_text' => "Pasal 1\r\nIsi pasal satu yang ditempel manual."]);

    expect(app(ExtractComparisonText::class)->handle($source))->toBe("Pasal 1\nIsi pasal satu yang ditempel manual.")
        ->and($source->fresh()->extraction_method)->toBe(ExtractionMethod::Pasted);
});

it('rejects a document with no readable text', function () {
    $source = $this->comparison->sources()->create(['side' => 'b', 'kind' => SourceKind::Paste, 'pasted_text' => ' ']);

    app(ExtractComparisonText::class)->handle($source);
})->throws(ExtractionException::class);
