<?php

use App\Support\Dcs\Pdf\PdfEngineException;
use Tests\Support\PdfGolden;
use Tests\Support\PdfParity;

/*
 * Parity of the PHP watermark with the Node engine. The goldens in
 * tests/Fixtures/pdf-golden are what Node produced for the same payloads; these tests
 * are blocking: the PHP engine may not become the default while one fails.
 */

beforeEach(function () {
    if (! PdfParity::available()) {
        $this->markTestSkipped('poppler (pdftoppm, pdftotext, pdfinfo) is needed for the parity comparison.');
    }
});

it('has golden watermark cases to compare against', function () {
    expect(PdfGolden::cases('watermark'))->not->toBeEmpty();
});

it('draws the same watermark as the Node engine: pages, text and every pixel', function (string $id) {
    $golden = PdfGolden::pdf('watermark', $id);
    $actual = PdfGolden::engine()->watermark(PdfGolden::input('watermark', $id));

    $diff = PdfParity::compare($golden, $actual);

    expect($actual)->toStartWith('%PDF')
        ->and(PdfParity::pages($actual))->toBe(PdfParity::pages($golden))
        ->and(PdfParity::text($actual))->toBe(PdfParity::text($golden))
        ->and($diff['differingPixels'])->toBe(0, "page {$diff['worstPage']} differs (max channel {$diff['maxChannelDifference']})");
})->with(PdfGolden::cases('watermark'));

it('never changes the source file and puts the viewer in the copy', function () {
    $input = PdfGolden::input('watermark', 'short-iso-effective');
    $before = hash('sha256', $input['pdf']);

    $copy = PdfGolden::engine()->watermark($input);

    expect(hash('sha256', $input['pdf']))->toBe($before)
        ->and(hash('sha256', $copy))->not->toBe($before)
        ->and(PdfParity::text($copy))->toContain('Accessed by Dewi Ayu Kusumawardhani')
        ->and(PdfParity::text(PdfGolden::engine()->watermark(['viewerName' => 'Budi Santoso'] + $input)))->toContain('Accessed by Budi Santoso');
});

it('fills the live header cells only when the compose step recorded where', function () {
    $with = PdfGolden::input('watermark', 'short-iso-effective');
    $without = ['headerLayout' => null] + $with;

    expect(PdfParity::text(PdfGolden::engine()->watermark($with)))->toContain('Status Dokumen : CONTROLLED')->toContain('20/11/2026')
        ->and(PdfParity::text(PdfGolden::engine()->watermark($without)))->not->toContain('Status Dokumen : CONTROLLED');
});

it('refuses a character outside WinAnsi the way the Node engine did', function () {
    PdfGolden::engine()->watermark(['viewerName' => 'Dewi → Budi'] + PdfGolden::input('watermark', 'short-iso-effective'));
})->throws(PdfEngineException::class, 'WinAnsi cannot encode "→" (0x2192)');

it('answers a clear error for something that is not a PDF', function () {
    PdfGolden::engine()->watermark(['pdf' => 'not a pdf'] + PdfGolden::input('watermark', 'short-iso-effective'));
})->throws(PdfEngineException::class, 'The file is not a PDF.');

it('answers a clear error for a damaged PDF', function () {
    PdfGolden::engine()->watermark(['pdf' => "%PDF-1.7\nbroken"] + PdfGolden::input('watermark', 'short-iso-effective'));
})->throws(PdfEngineException::class);
