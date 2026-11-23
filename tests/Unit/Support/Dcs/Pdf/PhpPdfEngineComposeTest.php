<?php

use App\Support\Dcs\Pdf\PdfEngineException;
use App\Support\Dcs\Pdf\PhpPdfEngine;
use Tests\Support\PdfGolden;
use Tests\Support\PdfParity;

/*
 * Parity of the PHP compose with the Node engine: for the same payload the goldens in
 * tests/Fixtures/pdf-golden are what Node drew. Pages, text, every pixel and the
 * layouts handed back for the sign and watermark steps must all match.
 */

beforeEach(function () {
    if (! PdfParity::available()) {
        $this->markTestSkipped('poppler (pdftoppm, pdftotext, pdfinfo) is needed for the parity comparison.');
    }
});

// "flow-image" is a deliberate deviation from Node: an uploaded diagram now sits in the same titled
// frame as one drawn from steps (see the test at the end of this file); its golden is the old layout.
$parityCases = array_diff_key(PdfGolden::composeCases(), ['flow-image' => true]);

it('has golden compose cases to compare against', function () {
    expect(PdfGolden::composeCases())->not->toBeEmpty();
});

it('composes the same document as the Node engine: pages, text and every pixel', function (string $id) {
    $golden = PdfGolden::pdf('compose', $id);
    $composed = PdfGolden::engine()->compose(PdfGolden::composeInput($id));

    $diff = PdfParity::compare($golden, $composed['pdf']);

    expect($composed['pdf'])->toStartWith('%PDF')
        ->and(PdfParity::pages($composed['pdf']))->toBe(PdfParity::pages($golden))
        ->and(PdfParity::text($composed['pdf']))->toBe(PdfParity::text($golden))
        ->and($diff['differingPixels'])->toBe(0, "page {$diff['worstPage']} differs (max channel {$diff['maxChannelDifference']})");
})->with($parityCases);

it('hands back the same sheet page, signature layout and header cells as the Node engine', function (string $id) {
    $composed = PdfGolden::engine()->compose(PdfGolden::composeInput($id));
    $golden = PdfGolden::composeMeta($id);
    $round = function (mixed $v) use (&$round): mixed {
        return is_array($v) ? array_map($round, $v) : (is_numeric($v) ? round((float) $v, 6) : $v);
    };

    expect($composed['signatureSheetPageIndex'])->toBe($golden['signatureSheetPageIndex'])
        ->and($round($composed['sigLayout']))->toEqual($round($golden['sigLayout']))
        ->and($round($composed['headerLayout'] ?? null))->toEqual($round($golden['headerLayout']));
})->with($parityCases);

it('draws the signature into the sheet it composed, like the Node engine did', function () {
    $composed = PdfGolden::engine()->compose(PdfGolden::composeInput('many-signers'));
    $input = PdfGolden::input('sign', 'many-signers-step1');

    $stamped = PdfGolden::engine()->stampSignature(['pdf' => $composed['pdf'], 'sigLayout' => $composed['sigLayout'], 'pageIndex' => $composed['signatureSheetPageIndex']] + $input);

    expect(PdfParity::compare(PdfGolden::pdf('sign', 'many-signers-step1'), $stamped)['differingPixels'])->toBe(0);
});

it('stamps the watermark on the document it composed, like the Node engine did', function () {
    $composed = PdfGolden::engine()->compose(PdfGolden::composeInput('short-iso'));
    $input = PdfGolden::input('watermark', 'short-iso-effective');

    $copy = PdfGolden::engine()->watermark(['pdf' => $composed['pdf'], 'headerLayout' => $composed['headerLayout']] + $input);

    expect(PdfParity::compare(PdfGolden::pdf('watermark', 'short-iso-effective'), $copy)['differingPixels'])->toBe(0);
});

it('refuses a character outside WinAnsi the way the Node engine did', function () {
    $manifest = json_decode(file_get_contents(PdfGolden::root().'/manifest.json'), true);

    expect($manifest['entries']['compose/unicode-outside-winansi']['error'])->toContain('WinAnsi cannot encode "→" (0x2192)');

    PdfGolden::engine()->compose(PdfGolden::composeInput('unicode-outside-winansi'));
})->throws(PdfEngineException::class, 'WinAnsi cannot encode "→" (0x2192)');

it('keeps the numbering time zone out of the layout but in the dates', function () {
    $input = PdfGolden::composeInput('multipage-standard');
    $input['revisionHistory'][1]['effectiveAt'] = '2025-09-14T20:00:00.000Z';

    $text = PdfParity::text((new PhpPdfEngine('Asia/Jakarta'))->compose($input)['pdf']);
    $utc = PdfParity::text((new PhpPdfEngine('UTC'))->compose($input)['pdf']);

    expect($text)->toContain('15/9/2025')->and($utc)->toContain('14/9/2025');
});

it('can edit what it wrote: two signatures in a row land as the Node engine drew them', function () {
    $engine = PdfGolden::engine();
    $composed = $engine->compose(PdfGolden::composeInput('many-signers'));
    $first = PdfGolden::input('sign', 'many-signers-step1');
    $second = PdfGolden::input('sign', 'many-signers-step2');

    $one = $engine->stampSignature(['pdf' => $composed['pdf']] + $first);
    $two = $engine->stampSignature(['pdf' => $one] + $second);

    expect(PdfParity::compare(PdfGolden::pdf('sign', 'many-signers-step2'), $two)['differingPixels'])->toBe(0)
        ->and(PdfParity::text($two))->toContain($first['signerName'])->toContain($second['signerName']);
});

it('draws an uploaded flow picture in the same titled frame as a diagram made from steps', function () {
    $input = PdfGolden::composeInput('flow-image');
    $text = PdfParity::text(PdfGolden::engine()->compose($input)['pdf']);

    expect($text)->toContain('ALUR DARI GAMBAR - DIAGRAM ALIR')->toContain($input['documentNumber'])->not->toContain('Diagram Alir');
});

it('keeps the picture inside the frame whatever its size', function () {
    $input = PdfGolden::composeInput('flow-image');
    $pages = PdfParity::pages(PdfGolden::engine()->compose($input)['pdf']);

    expect($pages)->toBeLessThanOrEqual(PdfParity::pages(PdfGolden::pdf('compose', 'flow-image')));
});
