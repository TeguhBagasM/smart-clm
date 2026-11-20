<?php

use App\Support\Dcs\Pdf\PdfEngineException;
use App\Support\Dcs\Pdf\PhpPdfEngine;
use Tests\Support\PdfGolden;
use Tests\Support\PdfParity;

/*
 * Parity of the PHP signature stamp with the Node engine, step by step: each step
 * stamps the file the Node engine had before it (the golden of the step before), so
 * one difference cannot hide behind another.
 */

beforeEach(function () {
    if (! PdfParity::available()) {
        $this->markTestSkipped('poppler (pdftoppm, pdftotext, pdfinfo) is needed for the parity comparison.');
    }
});

it('stamps the same signature as the Node engine: pages, text and every pixel', function (string $id) {
    $golden = PdfGolden::pdf('sign', $id);
    $actual = PdfGolden::engine()->stampSignature(PdfGolden::input('sign', $id));

    $diff = PdfParity::compare($golden, $actual);

    expect($actual)->toStartWith('%PDF')
        ->and(PdfParity::pages($actual))->toBe(PdfParity::pages($golden))
        ->and(PdfParity::text($actual))->toBe(PdfParity::text($golden))
        ->and($diff['differingPixels'])->toBe(0, "page {$diff['worstPage']} differs (max channel {$diff['maxChannelDifference']})");
})->with(PdfGolden::cases('sign'));

it('puts the name, role and the Indonesian date into the box of the column', function () {
    $input = PdfGolden::input('sign', 'many-signers-step1');

    $text = PdfParity::text(PdfGolden::engine()->stampSignature($input));

    expect($text)->toContain($input['signerName'])->toContain('20 Nov 2026');
});

it('reads the date in the configured zone', function () {
    $input = ['signedAtIso' => '2026-11-20T18:30:00.000Z'] + PdfGolden::input('sign', 'many-signers-step1');

    $jakarta = PdfParity::text((new PhpPdfEngine('Asia/Jakarta'))->stampSignature($input));
    $utc = PdfParity::text((new PhpPdfEngine('UTC'))->stampSignature($input));

    expect($jakarta)->toContain('21 Nov 2026')->and($utc)->toContain('20 Nov 2026');
});

it('refuses a column that is not on the sheet, with the number of columns', function () {
    PdfGolden::engine()->stampSignature(['columnIndex' => 99] + PdfGolden::input('sign', 'many-signers-step1'));
})->throws(PdfEngineException::class, 'Signature column index 99 out of range (0..8)');

it('refuses a column stamp without the signature groups', function () {
    PdfGolden::engine()->stampSignature(['groups' => null] + PdfGolden::input('sign', 'many-signers-step1'));
})->throws(PdfEngineException::class, 'groups and columnIndex are required for mode column.');
