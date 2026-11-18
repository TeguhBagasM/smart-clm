<?php

namespace App\Support\Dcs\Header;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The letterhead (kop) of a document type: an optional logo block beside the
 * first rows, then rows of cells. A cell's `span` is its share of the row in
 * twelfths; the engine scales a row whose spans do not add up to twelve.
 *
 * @phpstan-type Cell array{span: int, label: string, field: string, bold: bool, italic: bool, uppercase: bool, align: string, fill: string}
 * @phpstan-type Row array{height: int, cells: list<Cell>}
 * @phpstan-type Layout array{logo: array{enabled: bool, rows: int, width: int}, rows: list<Row>}
 */
final class HeaderLayout
{
    public const MAX_ROWS = 12;

    public const MAX_CELLS = 4;

    /**
     * The layout of the Indonesian ISO-style SOP: logo beside the first four rows.
     *
     * @return Layout
     */
    public static function default(): array
    {
        $band = ['bold' => true, 'uppercase' => true, 'align' => 'center'];

        return [
            'logo' => ['enabled' => true, 'rows' => 4, 'width' => 22],
            'rows' => [
                ['height' => 20, 'cells' => [self::cell('docTypeName', '', 12, [...$band, 'fill' => 'dark'])]],
                ['height' => 20, 'cells' => [self::cell('title', '', 12, [...$band, 'uppercase' => false])]],
                ['height' => 18, 'cells' => [self::cell('documentNumber', 'Nomor Dokumen', 7), self::cell('issuedDate', 'Tanggal Diterbitkan', 5)]],
                ['height' => 18, 'cells' => [self::cell('revisionLabel', 'Nomor Revisi/Tahun', 7), self::cell('page', 'Nomor Halaman', 5)]],
                ['height' => 18, 'cells' => [self::cell('language', 'Bahasa', 7), self::cell('documentLevel', 'Tingkat Dokumen', 5)]],
                ['height' => 18, 'cells' => [self::cell('processOwner', 'Pemilik Proses', 7), self::cell('status', 'Status Dokumen', 5)]],
            ],
        ];
    }

    /**
     * @param  array{bold?: bool, italic?: bool, uppercase?: bool, align?: string, fill?: string}  $style
     * @return Cell
     */
    private static function cell(string $field, string $label, int $span, array $style = []): array
    {
        return [
            'span' => $span,
            'label' => $label,
            'field' => $field,
            'bold' => $style['bold'] ?? false,
            'italic' => $style['italic'] ?? false,
            'uppercase' => $style['uppercase'] ?? false,
            'align' => $style['align'] ?? 'left',
            'fill' => $style['fill'] ?? 'none',
        ];
    }

    /**
     * Validation rules for a layout under `$prefix` (e.g. "headerLayout").
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rules(string $prefix): array
    {
        return [
            $prefix => ['nullable', 'array'],
            "$prefix.logo" => ['required_with:'.$prefix, 'array'],
            "$prefix.logo.enabled" => ['required_with:'.$prefix, 'boolean'],
            "$prefix.logo.rows" => ['required_with:'.$prefix, 'integer', 'min:1', 'max:'.self::MAX_ROWS],
            "$prefix.logo.width" => ['required_with:'.$prefix, 'integer', 'min:10', 'max:40'],
            "$prefix.rows" => ['required_with:'.$prefix, 'array', 'min:1', 'max:'.self::MAX_ROWS],
            "$prefix.rows.*.height" => ['required', 'integer', 'min:12', 'max:60'],
            "$prefix.rows.*.cells" => ['required', 'array', 'min:1', 'max:'.self::MAX_CELLS],
            "$prefix.rows.*.cells.*.span" => ['required', 'integer', 'min:1', 'max:12'],
            "$prefix.rows.*.cells.*.label" => ['nullable', 'string', 'max:60'],
            "$prefix.rows.*.cells.*.field" => ['required', Rule::enum(HeaderField::class)],
            "$prefix.rows.*.cells.*.bold" => ['boolean'],
            "$prefix.rows.*.cells.*.italic" => ['boolean'],
            "$prefix.rows.*.cells.*.uppercase" => ['boolean'],
            "$prefix.rows.*.cells.*.align" => ['required', Rule::in(['left', 'center', 'right'])],
            "$prefix.rows.*.cells.*.fill" => ['required', Rule::in(['none', 'muted', 'dark'])],
        ];
    }

    /**
     * Fills in missing flags and clamps the logo rows to the rows that exist.
     *
     * @param  array<string, mixed>  $input  a layout that passed validation
     * @return Layout
     */
    public static function normalize(array $input): array
    {
        $rows = array_map(fn (array $row): array => [
            'height' => (int) $row['height'],
            'cells' => array_map(fn (array $cell): array => [
                'span' => (int) $cell['span'],
                'label' => (string) ($cell['label'] ?? ''),
                'field' => (string) $cell['field'],
                'bold' => (bool) ($cell['bold'] ?? false),
                'italic' => (bool) ($cell['italic'] ?? false),
                'uppercase' => (bool) ($cell['uppercase'] ?? false),
                'align' => (string) $cell['align'],
                'fill' => (string) $cell['fill'],
            ], array_values($row['cells'])),
        ], array_values($input['rows']));

        return [
            'logo' => [
                'enabled' => (bool) $input['logo']['enabled'],
                'rows' => min((int) $input['logo']['rows'], count($rows)),
                'width' => (int) $input['logo']['width'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * The layout in force: the stored one, or the default.
     *
     * @param  array<string, mixed>|null  $stored
     * @return Layout
     */
    public static function effective(?array $stored): array
    {
        return $stored === null ? self::default() : self::normalize($stored);
    }
}
