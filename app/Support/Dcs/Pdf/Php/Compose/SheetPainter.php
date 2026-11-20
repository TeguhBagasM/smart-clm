<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

use App\Support\Dcs\Pdf\Php\Draw\IdDate;
use App\Support\Dcs\Pdf\Php\Draw\PageCanvas;
use App\Support\Dcs\Pdf\Php\Draw\SignatureSheet as Slots;
use App\Support\Dcs\Pdf\Php\Fonts\StandardFont;

/**
 * The closing pages: the signature sheet ("Lembar Pengesahan"), the revision history,
 * the appendix list and the confidentiality notice (port of the matching functions of
 * dcs/pdf-compose.ts). The geometry of the sheet is shared with the signature stamp.
 */
final class SheetPainter
{
    private const BLUE = [0.16, 0.32, 0.75];

    public function __construct(private Page $doc, private string $timezone) {}

    /**
     * Draws the sheet on a new page; returns that page's index.
     *
     * @param  list<array<string, mixed>>  $groups
     * @param  array{marginX: float, colTopY: float, contentW: float, style: string|null, serif: bool}  $sig
     */
    public function signatures(StandardFont $bold, StandardFont $regular, array $groups, array $sig): int
    {
        $index = count($this->doc->pages);
        $page = $this->doc->addPage();

        if ($sig['style'] === 'table') {
            $this->signatureTable($page, $bold, $regular, $groups, $sig);

            return $index;
        }

        $page->text('LEMBAR PENGESAHAN', $bold, 13, $sig['marginX'], $sig['colTopY'] + 60, self::BLUE);
        $slots = Slots::slots($groups, $sig);

        foreach ($groups as $gi => $group) {
            $groupSlots = array_values(array_filter($slots, fn (array $s): bool => $s['groupIndex'] === $gi));

            if ($groupSlots === []) {
                continue;
            }

            if (! empty($group['heading'])) {
                $page->text($group['heading'], $bold, 10, $sig['marginX'], $groupSlots[0]['colTopY'] + Slots::HEADING_H - 4, [0.16, 0.16, 0.2]);
            }

            foreach ($groupSlots as $slot) {
                $column = array_values($group['columns'])[$slot['colIndexInGroup']];
                $cx = $slot['x'] + $slot['colW'] / 2;
                $page->text($column['label'], $bold, 10, $cx - $bold->widthOfTextAtSize($column['label'], 10) / 2, $slot['colTopY'], [0.16, 0.16, 0.2]);

                if (! empty($column['roleName'])) {
                    $page->text($column['roleName'], $regular, 8.5, $cx - $regular->widthOfTextAtSize($column['roleName'], 8.5) / 2, $slot['colTopY'] - 13, [0.4, 0.42, 0.48]);
                }

                $page->rect($slot['x'] + 8, $slot['colTopY'] - Slots::BAND_BODY_H, $slot['colW'] - 16, Slots::BAND_BODY_H - 12, null, [0.75, 0.76, 0.8], 0.75);

                foreach (['Nama', 'Jabatan', 'Tanggal'] as $f => $label) {
                    $page->text("{$label}:", $regular, 8, $slot['x'] + 12, $slot['colTopY'] - Slots::NAME_LINE_OFFSET - $f * Slots::LABEL_GAP, [0.5, 0.52, 0.58]);
                }
            }
        }

        return $index;
    }

    /** The y of the lower edge of the table-style sheet. */
    public function tableBottom(array $groups, array $sig): float
    {
        $slots = Slots::slots($groups, $sig);
        $last = $slots[count($slots) - 1] ?? null;

        return $last !== null ? $last['colTopY'] - Slots::T_BLOCK : $sig['colTopY'] + 50 - Slots::T_BAND;
    }

    /**
     * The revision table under the sheet, on the same page.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function revisionBelowSheet(PageCanvas $page, float $top, array $rows, StandardFont $bold, StandardFont $regular): void
    {
        $l = $this->doc->layout;
        $w = $l->contentW;
        $row = 24;
        $widths = [$w * 0.2, $w * 0.25, $w * 0.55];
        $y = $top - $row - 10;
        $page->rect($l->mLeft, $y, $w, $row, null, [0, 0, 0], 0.75);
        $this->centered($page, 'Revisi Dan Riwayat Perubahan', $bold, 11, $l->mLeft, $w, $y + 8);
        $cells = function (array $texts, StandardFont $font, bool $center) use ($page, &$y, $row, $widths, $l): void {
            $y -= $row;
            $x = $l->mLeft;

            foreach ($texts as $i => $t) {
                $page->rect($x, $y, $widths[$i], $row, null, [0, 0, 0], 0.75);
                $clipped = TextFlow::wrap($t, $font, 10, $widths[$i] - 8)[0] ?? '';

                if ($center) {
                    $this->centered($page, $clipped, $font, 10, $x, $widths[$i], $y + 8);
                } else {
                    $page->text($clipped, $font, 10, $x + 5, $y + 8, [0, 0, 0]);
                }

                $x += $widths[$i];
            }
        };
        $cells(['Rev. No.', 'Tanggal', 'Catatan'], $bold, true);
        $body = $this->revisionRows($rows);

        for ($i = 0; $i < max(3, count($body)); $i++) {
            $cells($body[$i] ?? ['', '', ''], $regular, false);
        }
    }

    /** @param  list<array<string, mixed>>  $rows */
    public function revisionPage(array $rows, StandardFont $bold, StandardFont $regular, BodyPainter $body): void
    {
        $l = $this->doc->layout;
        $page = $this->doc->addPage();
        $page->text('Revisi Dan Riwayat Perubahan', $bold, 13, $l->mLeft, $l->contentTopY, self::BLUE);
        $table = [...$this->revisionRows($rows), ...array_fill(0, max(0, 3 - count($rows)), ['', '', ''])];
        $body->table(new Cursor($page, $l->contentTopY - 30), ['Rev. No', 'Tanggal', 'Catatan'], $table, $bold, $regular);
    }

    /** @param  list<array<string, string>>  $items */
    public function appendix(array $items, StandardFont $bold, StandardFont $regular): void
    {
        if ($items === []) {
            return;
        }

        $l = $this->doc->layout;
        $page = $this->doc->addPage();
        $page->text('Lampiran', $bold, 13, $l->mLeft, $l->contentTopY, self::BLUE);
        $y = $l->contentTopY - 30;

        foreach ($items as $no => $item) {
            if ($y - 40 < $l->mBottom) {
                $page = $this->doc->addPage();
                $y = $l->contentTopY - 20;
            }

            $page->text(($no + 1).". {$item['label']}", $bold, 10, $l->mLeft, $y, [0.15, 0.16, 0.2]);
            $y -= 14;

            foreach (TextFlow::wrap($item['url'], $regular, 9, $l->contentW - 12) as $line) {
                $page->text($line, $regular, 9, $l->mLeft + 12, $y, self::BLUE);
                $y -= 12;
            }

            $y -= 10;
        }
    }

    public function confidentiality(string $text, StandardFont $regular): void
    {
        $l = $this->doc->layout;
        $page = $this->doc->addPage();
        $y = $l->contentTopY;
        $lineHeight = 8.5 * 1.4;

        foreach (TextFlow::wrap($text, $regular, 8.5, $l->contentW) as $line) {
            if ($y - $lineHeight < $l->mBottom) {
                break;
            }

            $page->text($line, $regular, 8.5, $l->mLeft, $y - $lineHeight, [0.45, 0.47, 0.52]);
            $y -= $lineHeight;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @param  array{marginX: float, colTopY: float, contentW: float, style: string|null, serif: bool}  $sig
     */
    private function signatureTable(PageCanvas $page, StandardFont $bold, StandardFont $regular, array $groups, array $sig): void
    {
        $top = $sig['colTopY'] + 50;
        $page->rect($sig['marginX'], $top - Slots::T_BAND, $sig['contentW'], Slots::T_BAND, [0.14, 0.21, 0.38], [0, 0, 0], 0.75);
        $this->centered($page, 'Pengesahan Dokumen', $bold, 11, $sig['marginX'], $sig['contentW'], $top - Slots::T_BAND + 9, [1, 1, 1]);
        $slots = Slots::slots($groups, $sig);

        foreach ($groups as $gi => $group) {
            $first = null;

            foreach ($slots as $s) {
                if ($s['groupIndex'] === $gi) {
                    $first = $s;
                    break;
                }
            }

            if ($first !== null && ! empty($group['heading'])) {
                $page->rect($sig['marginX'], $first['colTopY'], $sig['contentW'], Slots::T_HEAD, [0.92, 0.93, 0.95], [0, 0, 0], 0.75);
                $this->centered($page, $group['heading'], $bold, 9.5, $sig['marginX'], $sig['contentW'], $first['colTopY'] + 6);
            }
        }

        foreach ($slots as $slot) {
            $column = array_values($groups[$slot['groupIndex']]['columns'])[$slot['colIndexInGroup']];
            $label = preg_match('/[:：]\s*$/u', $column['label']) === 1 ? $column['label'] : $column['label'].':';

            foreach ([[Slots::T_LABEL, 0], [Slots::T_SIGN, Slots::T_LABEL], [Slots::T_NAME, Slots::T_LABEL + Slots::T_SIGN], [Slots::T_ROLE, Slots::T_LABEL + Slots::T_SIGN + Slots::T_NAME]] as [$h, $off]) {
                $page->rect($slot['x'], $slot['colTopY'] - $off - $h, $slot['colW'], $h, null, [0, 0, 0], 0.75);
            }

            $this->centered($page, $label, $regular, 11, $slot['x'], $slot['colW'], $slot['colTopY'] - Slots::T_LABEL + 8);

            if (! empty($column['roleName'])) {
                $this->centered($page, $column['roleName'], $regular, 11, $slot['x'], $slot['colW'], $slot['colTopY'] - Slots::T_BLOCK + 7);
            }
        }
    }

    /** @param  array<int, float>  $color */
    private function centered(PageCanvas $page, string $text, StandardFont $font, float $size, float $x, float $w, float $y, array $color = [0, 0, 0]): void
    {
        $page->text($text, $font, $size, $x + ($w - $font->widthOfTextAtSize($text, $size)) / 2, $y, $color);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<list<string>>
     */
    private function revisionRows(array $rows): array
    {
        return array_map(fn (array $r): array => ["{$r['major']}.{$r['minor']}", ! empty($r['effectiveAt']) ? IdDate::numeric($r['effectiveAt'], $this->timezone) : '-', ! empty($r['changeSummary']) ? $r['changeSummary'] : '-'], $rows);
    }
}
