<?php

use App\Support\Dcs\Header\HeaderLayout;
use Tests\Support\PdfGolden;
use Tests\Support\PdfParity;

it('composes with every letterhead template, and a letterhead with no lines prints differently from the grid', function () {
    if (! PdfParity::available()) {
        $this->markTestSkipped('poppler (pdftoppm, pdftotext, pdfinfo) is needed for the pixel comparison.');
    }

    $compose = function (string $key): string {
        $input = PdfGolden::composeInput('iso-header-alpha-logo-margins');
        $input['header'] = ['layout' => HeaderLayout::preset($key)['layout'], 'values' => ['documentNumber' => 'SOP-QA-001', 'title' => 'Judul', 'companyName' => 'PT Uji', 'docTypeName' => 'SOP', 'issueNumber' => '1', 'revisionIndex' => '0']];

        return PdfGolden::engine()->compose($input)['pdf'];
    };

    foreach (array_column(HeaderLayout::presets(), 'key') as $key) {
        expect($compose($key))->toStartWith('%PDF');
    }

    expect(PdfParity::compare($compose('sop'), $compose('logo-garis'))['differingPixels'])->toBeGreaterThan(0);
});
