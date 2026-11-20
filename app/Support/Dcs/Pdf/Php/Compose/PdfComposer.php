<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

use App\Support\Dcs\Pdf\Php\Draw\EmbeddedImage;
use App\Support\Dcs\Pdf\Php\Draw\IdDate;
use App\Support\Dcs\Pdf\Php\Fonts\StandardFont;
use App\Support\Dcs\Pdf\Php\Model\PdfFile;
use Throwable;

/**
 * Composes a document's PDF from its structured content, with no Node process (port of
 * composeDocumentPdf in dcs/pdf-compose.ts): the same pages, in the same order, at the
 * same coordinates, in the same standard fonts, so old and new documents look alike.
 */
final class PdfComposer
{
    public function __construct(private string $timezone) {}

    /**
     * @param  array<string, mixed>  $input  the payload of PdfEngine::compose
     * @return array{pdf: string, signatureSheetPageIndex: int|null, sigLayout: array<string, mixed>|null, headerLayout: array<string, mixed>|null}
     */
    public function compose(array $input): array
    {
        $iso = ($input['headerStyle'] ?? null) === 'iso';
        $formal = ($input['bodyStyle'] ?? null) === 'formal';
        $header = $input['header'] ?? null;
        $layout = Layout::build($input['margins'] ?? null, $iso, $header ? HeaderGeometry::height($header['layout']) : Layout::HEADER_BOX_H);
        $doc = new Page(PdfFile::blank('Smart CLM'), $layout, $formal);
        $body = new BodyPainter($doc);
        $headers = new HeaderPainter($doc);
        $sheets = new SheetPainter($doc, $this->timezone);
        $cur = new Cursor($doc->addPage(), $layout->contentTopY);
        $logo = $this->logo($doc, $input['letterhead'] ?? null);
        $issuedDate = IdDate::numeric($input['issuedDateIso'] ?? null, $this->timezone);

        if (! $iso) {
            $cur->y = $headers->memoBox($cur->page, $input['letterhead'] ?? null, ['documentNumber' => $input['documentNumber'], 'minor' => $input['minor'], 'major' => $input['major'], 'issuedDate' => $issuedDate], $logo);

            foreach (TextFlow::wrap($input['title'], $doc->bold, 15, $layout->contentW) as $line) {
                $doc->ensureSpace($cur, 20);
                $cur->page->text($line, $doc->bold, 15, $layout->mLeft, $cur->y - 15, [0.09, 0.1, 0.14]);
                $cur->y -= 20;
            }

            $cur->y -= 8;
        }

        $this->sections($input, $doc, $body, $cur, $formal);
        $this->flow($input, $doc);

        $sig = ['marginX' => $layout->mLeft, 'colTopY' => $layout->contentTopY - 60, 'contentW' => $layout->contentW, 'style' => 'table', 'serif' => $formal];
        $groups = ! empty($input['signatureGroups']) ? $input['signatureGroups'] : (! empty($input['signatureColumns']) ? [['columns' => $input['signatureColumns']]] : null);
        $sheetBold = $formal ? $doc->bodyBold : $doc->bold;
        $sheetRegular = $formal ? $doc->bodyRegular : $doc->regular;
        $signatureSheetPageIndex = null;
        $revisionDrawn = false;

        if ($groups !== null) {
            $signatureSheetPageIndex = $sheets->signatures($sheetBold, $sheetRegular, $groups, $sig);
            $history = $input['revisionHistory'] ?? [];
            $bottom = $sheets->tableBottom($groups, $sig);

            if (count($history) <= 4 && $bottom - (48 + max(3, count($history)) * 24) > $layout->mBottom) {
                $sheets->revisionBelowSheet($doc->pages[$signatureSheetPageIndex], $bottom - 4, $history, $doc->bodyBold, $doc->bodyRegular);
                $revisionDrawn = true;
            }
        }

        if (TextFlow::trim((string) ($input['confidentialityNotice'] ?? '')) !== '') {
            $sheets->confidentiality($input['confidentialityNotice'], $doc->regular);
        }

        if (! $revisionDrawn) {
            $sheets->revisionPage($input['revisionHistory'] ?? [], $sheetBold, $sheetRegular, $body);
        }

        $sheets->appendix($input['appendix'] ?? [], $doc->bold, $doc->regular);
        $headerLayout = $iso ? $this->stampHeaders($input, $doc, $headers, $logo, $formal) : null;

        foreach ($doc->pages as $page) {
            $page->flush();
        }

        return ['pdf' => $doc->file->write(), 'signatureSheetPageIndex' => $signatureSheetPageIndex, 'sigLayout' => $groups !== null ? $sig : null, 'headerLayout' => $headerLayout];
    }

    /**
     * Tujuan, Ruang Lingkup, then the author's sections; the formal style numbers them all in Roman numerals.
     *
     * @param  array<string, mixed>  $input
     */
    private function sections(array $input, Page $doc, BodyPainter $body, Cursor $cur, bool $formal): void
    {
        $color = $formal ? [0, 0, 0] : [0.2, 0.22, 0.27];
        $opts = $formal ? ['lineFactor' => 1.5, 'justify' => true, 'offset' => BodyPainter::FORMAL_OFFSET] : [];
        $blocks = [
            ...(TextFlow::trim((string) ($input['purpose'] ?? '')) !== '' ? [['heading' => 'Tujuan', 'content' => $input['purpose']]] : []),
            ...(TextFlow::trim((string) ($input['scope'] ?? '')) !== '' ? [['heading' => 'Ruang Lingkup', 'content' => $input['scope']]] : []),
            ...array_filter($input['sections'] ?? [], fn (array $s): bool => TextFlow::trim($s['heading']) !== '' || TextFlow::trim($s['content']) !== '' || ! empty($s['table'])),
        ];

        foreach (array_values($blocks) as $index => $section) {
            $title = ($section['heading'] ?? '') !== '' ? $section['heading'] : '(Tanpa Judul Bagian)';
            $formal ? $body->formalHeading($cur, $index, $title, $doc->bodyBold) : $body->heading($cur, $title, $doc->bold);

            if (TextFlow::trim($section['content']) !== '') {
                $body->paragraph($cur, $section['content'], $doc->bodyRegular, $doc->bodyBold, $formal ? 11 : 10.5, $color, $opts);
            }

            if (! empty($section['table']['columns'])) {
                $cur->y -= 4;
                $body->table($cur, $section['table']['columns'], $section['table']['rows'] ?? [], $doc->bodyBold, $doc->bodyRegular);
            }

            $cur->y -= 8;
        }
    }

    /** @param  array<string, mixed>  $input */
    private function flow(array $input, Page $doc): void
    {
        $flow = $input['flow'] ?? ['mode' => 'none'];
        $l = $doc->layout;

        if (($flow['mode'] ?? null) === 'builder' && ! empty($flow['steps'])) {
            (new FlowPainter($doc, $input['title'], $input['documentNumber']))->draw($flow['steps'], $doc->bodyRegular, $doc->bodyBold, $flow['lanes'] ?? null, $flow['displayStyle'] ?? null);
        } elseif (($flow['mode'] ?? null) === 'image') {
            $page = $doc->addPage();
            $page->text('Diagram Alir', $doc->bold, 14, $l->mLeft, $l->contentTopY, [0.16, 0.32, 0.75]);
            $image = $doc->images->embed($doc->file, $flow['image'], $flow['mimeType']);
            $scale = min($l->contentW / $image->width, ($l->contentTopY - $l->mBottom - 30) / $image->height, 1);
            [$w, $h] = [$image->width * $scale, $image->height * $scale];
            $page->drawImage($image, (Layout::PAGE_W - $w) / 2, $l->contentTopY - 30 - $h, $w, $h);
        }
    }

    /**
     * Header and footer on every page, now that the page total is known; returns where the status and issue date go.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function stampHeaders(array $input, Page $doc, HeaderPainter $headers, ?EmbeddedImage $logo, bool $formal): array
    {
        $l = $doc->layout;
        $total = count($doc->pages);
        $header = $input['header'] ?? null;
        $fonts = $formal
            ? ['regular' => StandardFont::named('Times-Roman'), 'bold' => StandardFont::named('Times-Bold'), 'italic' => StandardFont::named('Times-Italic'), 'boldItalic' => StandardFont::named('Times-BoldItalic')]
            : ['regular' => $doc->regular, 'bold' => $doc->bold, 'italic' => StandardFont::named('Helvetica-Oblique'), 'boldItalic' => StandardFont::named('Helvetica-BoldOblique')];
        $meta = [
            'documentNumber' => $input['documentNumber'], 'title' => $input['title'], 'docTypeName' => ! empty($input['docTypeName']) ? $input['docTypeName'] : $input['docTypeCode'],
            'revisionLabel' => str_pad((string) $input['minor'], 2, '0', STR_PAD_LEFT).'/'.substr(date('Y'), -2), 'language' => ! empty($input['language']) ? $input['language'] : 'Indonesia',
            'documentLevel' => ! empty($input['documentLevel']) ? $input['documentLevel'] : 'Internal', 'processOwner' => ! empty($input['processOwner']) ? $input['processOwner'] : $input['department'],
        ];
        $reactive = [];

        foreach ($doc->pages as $i => $page) {
            $reactive = $header !== null
                ? $headers->configured($page, $fonts, $header, $l->mLeft, Layout::PAGE_H - $l->mTop, Layout::PAGE_W - $l->mLeft - $l->mRight, $i + 1, $total, $logo)
                : $headers->isoBox($page, $meta, $i + 1, $total, $logo);
            $headers->footer($page, $i + 1, $total);
        }

        return $reactive;
    }

    /** @param  array<string, mixed>|null  $letterhead */
    private function logo(Page $doc, ?array $letterhead): ?EmbeddedImage
    {
        if (empty($letterhead['logo'])) {
            return null;
        }

        try {
            return $doc->images->embed($doc->file, $letterhead['logo'], $letterhead['logoMimeType'] ?? '');
        } catch (Throwable) {
            return null;
        }
    }
}
