<?php

namespace App\Support\Dcs\Pdf\Php\Draw;

/**
 * Where each signature column of the sheet sits (port of layoutSignatureGroups in
 * dcs/pdf-compose.ts). Pure geometry shared by compose (which draws the boxes) and
 * sign (which fills them), so a signature always lands in its box.
 */
final class SignatureSheet
{
    public const LABEL_GAP = 18;

    public const NAME_LINE_OFFSET = 70;

    public const HEADING_H = 16;

    public const BAND_BODY_H = 100;

    public const BAND_GAP = 22;

    public const T_BAND = 26;

    public const T_HEAD = 20;

    public const T_LABEL = 24;

    public const T_SIGN = 84;

    public const T_NAME = 22;

    public const T_ROLE = 22;

    public const T_GAP = 14;

    public const T_BLOCK = 152;

    private const PAGE_W = 595.28;

    private const PAGE_H = 841.89;

    private const MARGIN = 56;

    /**
     * The layout the document was composed with, or the original fixed one for a document
     * composed before it was kept.
     *
     * @param  array<string, mixed>|null  $stored
     * @return array{marginX: float, colTopY: float, contentW: float, style: string|null, serif: bool}
     */
    public static function layout(?array $stored): array
    {
        return [
            'marginX' => $stored['marginX'] ?? self::MARGIN,
            'colTopY' => $stored['colTopY'] ?? self::PAGE_H - self::MARGIN - 60,
            'contentW' => $stored['contentW'] ?? self::PAGE_W - self::MARGIN * 2,
            'style' => $stored['style'] ?? null,
            'serif' => (bool) ($stored['serif'] ?? false),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @param  array{marginX: float, colTopY: float, contentW: float, style: string|null, serif: bool}  $sig
     * @return list<array{flatIndex: int, groupIndex: int, colIndexInGroup: int, x: float, colW: float, colTopY: float}>
     */
    public static function slots(array $groups, array $sig): array
    {
        $slots = [];
        $table = $sig['style'] === 'table';
        $top = $table ? $sig['colTopY'] + 50 - self::T_BAND : $sig['colTopY'];

        foreach (array_values($groups) as $g => $group) {
            $columns = array_values($group['columns'] ?? []);
            $heading = ! empty($group['heading']);
            $columnTop = $table ? $top - ($heading ? self::T_HEAD : 0) : $top - ($heading ? self::HEADING_H : 0);
            $width = $sig['contentW'] / max(count($columns), 1);

            foreach ($columns as $c => $_) {
                $slots[] = ['flatIndex' => count($slots), 'groupIndex' => $g, 'colIndexInGroup' => $c, 'x' => $sig['marginX'] + $width * $c, 'colW' => $width, 'colTopY' => $columnTop];
            }

            $top = $table ? $columnTop - self::T_BLOCK - self::T_GAP : $columnTop - self::BAND_BODY_H - self::BAND_GAP;
        }

        return $slots;
    }
}
