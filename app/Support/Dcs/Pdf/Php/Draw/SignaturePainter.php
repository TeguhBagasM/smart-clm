<?php

namespace App\Support\Dcs\Pdf\Php\Draw;

use App\Support\Dcs\Pdf\PdfEngineException;
use App\Support\Dcs\Pdf\Php\Fonts\StandardFont;
use App\Support\Dcs\Pdf\Php\Model\PdfFile;
use App\Support\Dcs\Pdf\Php\Model\Ref;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Stamps one signature (port of fillSignatureColumn in dcs/pdf-compose.ts and
 * injectSignatures in dcs/pdf-signature.ts): the name, role and date go into the box
 * of the signature sheet that compose drew, or one block on the last page of a plain
 * uploaded PDF. It is a text stamp, not a cryptographic signature.
 */
final class SignaturePainter
{
    private const BLACK = [0, 0, 0];

    public function __construct(private string $timezone) {}

    /**
     * @param  array<string, mixed>  $in  mode, signerName, signerRole, signedAtIso, groups, pageIndex, columnIndex, sigLayout
     *
     * @throws PdfEngineException
     */
    public function paint(PdfFile $file, array $in): void
    {
        $pages = $file->pages();
        $pool = new FontPool($file);

        if ($pages === []) {
            throw new PdfEngineException('The PDF has no pages.');
        }

        if (($in['mode'] ?? null) === 'generic') {
            $this->generic($file, $pool, $pages[count($pages) - 1], $in);

            return;
        }

        if (! is_array($in['groups'] ?? null) || ! is_int($in['columnIndex'] ?? null)) {
            throw new PdfEngineException('groups and columnIndex are required for mode column.');
        }

        $sig = SignatureSheet::layout(is_array($in['sigLayout'] ?? null) ? $in['sigLayout'] : null);
        $index = $in['pageIndex'] ?? null;
        $page = is_int($index) && isset($pages[$index]) ? $pages[$index] : $pages[count($pages) - 1];
        $slots = SignatureSheet::slots($in['groups'], $sig);
        $slot = $slots[$in['columnIndex']] ?? throw new PdfEngineException(sprintf('Signature column index %d out of range (0..%d)', $in['columnIndex'], count($slots) - 1));
        $column = array_values($in['groups'][$slot['groupIndex']]['columns'])[$slot['colIndexInGroup']] ?? [];
        $font = StandardFont::named($sig['style'] === 'table' && $sig['serif'] ? 'Times-Roman' : 'Helvetica');
        $canvas = new PageCanvas($file, $pool, $page['ref'], $page['width'], $page['height']);
        $date = IdDate::short($in['signedAtIso'], $this->timezone);

        $sig['style'] === 'table' ? $this->tableCell($canvas, $font, $slot, $column, $in, $date) : $this->plainCell($canvas, $font, $slot, $column, $in, $date);
        $canvas->flush();
    }

    /**
     * @param  array<string, mixed>  $slot
     * @param  array<string, mixed>  $column
     * @param  array<string, mixed>  $in
     */
    private function tableCell(PageCanvas $canvas, StandardFont $font, array $slot, array $column, array $in, string $date): void
    {
        $fit = function (string $text) use ($font, $slot): string {
            $s = $text;

            while (mb_strlen($s) > 3 && $font->widthOfTextAtSize($s, 11) > $slot['colW'] - 8) {
                $s = mb_substr($s, 0, -2);
            }

            return $s === $text ? $s : $s.'…';
        };
        $center = function (string $text, float $size, float $y, array $color = self::BLACK) use ($canvas, $font, $slot): void {
            $canvas->text($text, $font, $size, $slot['x'] + ($slot['colW'] - $font->widthOfTextAtSize($text, $size)) / 2, $y, $color);
        };

        $center($fit($in['signerName']), 11, $slot['colTopY'] - SignatureSheet::T_LABEL - SignatureSheet::T_SIGN - SignatureSheet::T_NAME + 7);

        if (empty($column['roleName'])) {
            $center($fit($in['signerRole']), 11, $slot['colTopY'] - SignatureSheet::T_BLOCK + 7);
        }

        $center($date, 8, $slot['colTopY'] - SignatureSheet::T_LABEL - SignatureSheet::T_SIGN + 6, [0.3, 0.3, 0.3]);
    }

    /**
     * @param  array<string, mixed>  $slot
     * @param  array<string, mixed>  $column
     * @param  array<string, mixed>  $in
     */
    private function plainCell(PageCanvas $canvas, StandardFont $font, array $slot, array $column, array $in, string $date): void
    {
        $values = [$in['signerName'], ! empty($column['roleName']) ? $column['roleName'] : $in['signerRole'], $date];
        $widthOfM = $font->widthOfTextAtSize('M', 8);

        foreach ($values as $f => $value) {
            $label = ['Nama', 'Jabatan', 'Tanggal'][$f];
            $labelWidth = $font->widthOfTextAtSize($label.': ', 8);
            $maxWidth = $slot['colW'] - 16 - $labelWidth - 4;
            $text = mb_strlen($value) * $widthOfM > $maxWidth ? mb_substr($value, 0, max(4, (int) floor($maxWidth / $widthOfM))) : $value;

            $canvas->text($text, $font, 8, $slot['x'] + 12 + $labelWidth, $slot['colTopY'] - SignatureSheet::NAME_LINE_OFFSET - $f * SignatureSheet::LABEL_GAP, [0.06, 0.32, 0.2]);
        }
    }

    /**
     * @param  array{ref: Ref, width: float, height: float}  $page
     * @param  array<string, mixed>  $in
     */
    private function generic(PdfFile $file, FontPool $pool, array $page, array $in): void
    {
        $font = StandardFont::named('Helvetica');
        $canvas = new PageCanvas($file, $pool, $page['ref'], $page['width'], $page['height']);
        $approvedAt = (new DateTimeImmutable($in['signedAtIso']))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');

        $canvas->text("{$in['signerName']} — {$in['signerRole']}", $font, 8, 60, 62, [0.1, 0.1, 0.1]);
        $canvas->text("Approved: {$approvedAt}", $font, 7, 60, 52, [0.35, 0.35, 0.35]);
        $canvas->flush();
    }
}
