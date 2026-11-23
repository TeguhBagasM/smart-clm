<?php

namespace App\Support\Dcs\Header;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The letterhead (kop) of a document type, schema version 2: an optional logo
 * block beside the first rows, then rows of cells. A cell's `width` is its share
 * of the row in percent (a row adds up to 100). Colours are stored as hex
 * (#RRGGBB or #RRGGBBAA), never as names; text colour may be "auto" (black or
 * white after the background). A cell's `text` is free text with `{field}`
 * placeholders taken from {@see HeaderField}.
 *
 * Version 1 (cells with `span` in twelfths and a `fill` name) is converted by
 * {@see self::fromV1()}, to the very same colours the engine used to draw.
 *
 * @phpstan-type Cell array{width: float, text: string, bold: bool, italic: bool, uppercase: bool, align: string, fontSize: int|null, background: string|null, color: string}
 * @phpstan-type Row array{height: int, cells: list<Cell>}
 * @phpstan-type Logo array{enabled: bool, source: string, assetId: string|null, side: string, rows: int, width: int, valign: string, padding: int}
 * @phpstan-type Layout array{version: int, border: string, logo: Logo, rows: list<Row>}
 */
final class HeaderLayout
{
    public const VERSION = 2;

    public const MAX_ROWS = 12;

    public const MAX_CELLS = 4;

    public const MAX_TEXT = 200;

    /** Widest gap from 100 (percent) that is still accepted as "adds up to 100". */
    private const WIDTH_TOLERANCE = 0.5;

    /**
     * The layout shipped for every document type that has none of its own: the Indonesian SOP
     * control form. The company name on top, the type and the title under it, then the number,
     * issue ("Terbit ke") and revision ("Revisi ke"), and the effective date and page. The logo
     * sits beside all five rows.
     *
     * @return Layout
     */
    public static function default(): array
    {
        $title = ['bold' => true, 'uppercase' => true, 'align' => 'center'];

        return [
            'version' => self::VERSION,
            'border' => HeaderPalette::BORDER,
            'logo' => ['enabled' => true, 'source' => 'company', 'assetId' => null, 'side' => 'left', 'rows' => 5, 'width' => 21, 'valign' => 'middle', 'padding' => 8],
            'rows' => [
                self::row(26, [self::cell('{companyName}', 100, [...$title, 'fontSize' => 14])]),
                self::row(18, [self::cell('{docTypeName}', 100, [...$title, 'fontSize' => 10])]),
                self::row(18, [self::cell('{title}', 100, [...$title, 'fontSize' => 10])]),
                self::row(20, [self::cell('No. Dokumen : {documentNumber}', 45), self::cell('Terbit ke : {issueNumber}', 26), self::cell('Revisi ke : {revisionIndex}', 29)]),
                self::row(20, [self::cell('Tanggal Efektif : {issuedDate}', 45), self::cell('Halaman : {pageOf}', 55)]),
            ],
        ];
    }

    /**
     * The earlier ISO-style layout (logo beside four rows, dark title band, six metadata cells).
     *
     * @return Layout
     */
    public static function legacyIso(): array
    {
        $band = ['bold' => true, 'uppercase' => true, 'align' => 'center'];

        return [
            'version' => self::VERSION,
            'border' => HeaderPalette::BORDER,
            'logo' => ['enabled' => true, 'source' => 'company', 'assetId' => null, 'side' => 'left', 'rows' => 4, 'width' => 22, 'valign' => 'middle', 'padding' => 8],
            'rows' => [
                self::row(20, [self::cell('{docTypeName}', 100, [...$band, 'background' => HeaderPalette::DARK, 'color' => HeaderPalette::WHITE])]),
                self::row(20, [self::cell('{title}', 100, [...$band, 'uppercase' => false])]),
                self::row(18, [self::cell('Nomor Dokumen : {documentNumber}', 58.33), self::cell('Tanggal Diterbitkan : {issuedDate}', 41.67)]),
                self::row(18, [self::cell('Nomor Revisi/Tahun : {revisionLabel}', 58.33), self::cell('Nomor Halaman : {page}', 41.67)]),
                self::row(18, [self::cell('Bahasa : {language}', 58.33), self::cell('Tingkat Dokumen : {documentLevel}', 41.67)]),
                self::row(18, [self::cell('Pemilik Proses : {processOwner}', 58.33), self::cell('Status Dokumen : {status}', 41.67)]),
            ],
        ];
    }

    /**
     * The starting points the editor offers. "sop" is the shipped default.
     *
     * @return list<array{key: string, name: string, description: string, layout: Layout}>
     */
    public static function presets(): array
    {
        $band = ['bold' => true, 'align' => 'center'];
        $logo = self::default()['logo'];

        return [
            ['key' => 'sop', 'name' => 'Kop SOP Indonesia', 'description' => 'Logo di kiri, nama perusahaan, jenis dan judul, lalu nomor, terbit ke, revisi ke, tanggal efektif, dan halaman.', 'layout' => self::default()],
            ['key' => 'iso', 'name' => 'Kop ISO', 'description' => 'Logo di kiri, pita judul biru tua, dan enam isian metadata.', 'layout' => self::legacyIso()],
            ['key' => 'sederhana', 'name' => 'Kop sederhana', 'description' => 'Logo di kiri dengan empat baris: jenis, judul, nomor, dan halaman.', 'layout' => [
                'version' => self::VERSION,
                'border' => HeaderPalette::BORDER,
                'logo' => [...$logo, 'rows' => 4, 'width' => 20],
                'rows' => [
                    self::row(20, [self::cell('{docTypeName}', 100, [...$band, 'uppercase' => true])]),
                    self::row(22, [self::cell('{title}', 100, $band)]),
                    self::row(18, [self::cell('Nomor : {documentNumber}', 60), self::cell('Revisi : {revisionLabel}', 40)]),
                    self::row(18, [self::cell('Terbit : {issuedDate}', 60), self::cell('Halaman : {page}', 40)]),
                ],
            ]],
            ['key' => 'tanpa-logo', 'name' => 'Kop tanpa logo', 'description' => 'Tiga baris selebar halaman, tanpa logo.', 'layout' => [
                'version' => self::VERSION,
                'border' => HeaderPalette::BORDER,
                'logo' => [...$logo, 'enabled' => false, 'rows' => 1],
                'rows' => [
                    self::row(20, [self::cell('{docTypeName}', 100, [...$band, 'uppercase' => true, 'background' => HeaderPalette::DARK, 'color' => HeaderPalette::WHITE])]),
                    self::row(20, [self::cell('{title}', 100, $band)]),
                    self::row(18, [self::cell('Nomor Dokumen : {documentNumber}', 60), self::cell('Status : {status}', 40)]),
                ],
            ]],
        ];
    }

    /**
     * @param  list<Cell>  $cells
     * @return Row
     */
    private static function row(int $height, array $cells): array
    {
        return ['height' => $height, 'cells' => $cells];
    }

    /**
     * @param  array{bold?: bool, italic?: bool, uppercase?: bool, align?: string, fontSize?: int, background?: string|null, color?: string}  $style
     * @return Cell
     */
    private static function cell(string $text, float $width, array $style = []): array
    {
        return [
            'width' => $width,
            'text' => $text,
            'bold' => $style['bold'] ?? false,
            'italic' => $style['italic'] ?? false,
            'uppercase' => $style['uppercase'] ?? false,
            'align' => $style['align'] ?? 'left',
            'fontSize' => $style['fontSize'] ?? null,
            'background' => $style['background'] ?? null,
            'color' => $style['color'] ?? HeaderPalette::TEXT,
        ];
    }

    /**
     * Structural validation rules for a layout under `$prefix` (e.g. "headerLayout").
     * What a rule cannot say (placeholders, widths adding up) is in {@see self::problems()}.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rules(string $prefix): array
    {
        $hex = 'regex:'.HeaderPalette::HEX_PATTERN;

        return [
            $prefix => ['nullable', 'array'],
            "$prefix.version" => ['required_with:'.$prefix, 'integer', Rule::in([self::VERSION])],
            "$prefix.border" => ['required_with:'.$prefix, 'string', $hex],
            "$prefix.logo" => ['required_with:'.$prefix, 'array'],
            "$prefix.logo.enabled" => ['required_with:'.$prefix, 'boolean'],
            "$prefix.logo.source" => ['required_with:'.$prefix, Rule::in(['company', 'custom'])],
            "$prefix.logo.assetId" => ['nullable', 'uuid'],
            "$prefix.logo.side" => ['required_with:'.$prefix, Rule::in(['left', 'right'])],
            "$prefix.logo.rows" => ['required_with:'.$prefix, 'integer', 'min:1', 'max:'.self::MAX_ROWS],
            "$prefix.logo.width" => ['required_with:'.$prefix, 'integer', 'min:10', 'max:40'],
            "$prefix.logo.valign" => ['required_with:'.$prefix, Rule::in(['top', 'middle', 'bottom'])],
            "$prefix.logo.padding" => ['required_with:'.$prefix, 'integer', 'min:0', 'max:20'],
            "$prefix.rows" => ['required_with:'.$prefix, 'array', 'min:1', 'max:'.self::MAX_ROWS],
            "$prefix.rows.*.height" => ['required', 'integer', 'min:12', 'max:60'],
            "$prefix.rows.*.cells" => ['required', 'array', 'min:1', 'max:'.self::MAX_CELLS],
            "$prefix.rows.*.cells.*.width" => ['required', 'numeric', 'min:5', 'max:100'],
            "$prefix.rows.*.cells.*.text" => ['nullable', 'string', 'max:'.self::MAX_TEXT],
            "$prefix.rows.*.cells.*.bold" => ['boolean'],
            "$prefix.rows.*.cells.*.italic" => ['boolean'],
            "$prefix.rows.*.cells.*.uppercase" => ['boolean'],
            "$prefix.rows.*.cells.*.align" => ['required', Rule::in(['left', 'center', 'right'])],
            "$prefix.rows.*.cells.*.fontSize" => ['nullable', 'integer', 'min:6', 'max:14'],
            "$prefix.rows.*.cells.*.background" => ['nullable', 'string', $hex],
            "$prefix.rows.*.cells.*.color" => ['required', 'string', 'regex:'.HeaderPalette::COLOR_PATTERN],
        ];
    }

    /**
     * What the structural rules cannot check, as `key => message`: unknown or
     * misplaced placeholders, a status or date placeholder used twice, rows whose
     * widths do not add up to 100.
     *
     * @param  array<string, mixed>  $layout
     * @return array<string, string>
     */
    public static function problems(array $layout): array
    {
        $problems = [];
        $reactiveSeen = [];
        $known = array_map(fn (HeaderField $field): string => $field->value, self::placeholderFields());

        foreach (array_values((array) ($layout['rows'] ?? [])) as $r => $row) {
            $cells = array_values((array) ($row['cells'] ?? []));
            $sum = array_sum(array_map(fn (mixed $cell): float => (float) ($cell['width'] ?? 0), $cells));

            if (abs($sum - 100) > self::WIDTH_TOLERANCE) {
                $problems["rows.$r.cells"] = 'Lebar sel dalam satu baris harus berjumlah 100%.';
            }

            foreach ($cells as $c => $cell) {
                $text = (string) ($cell['text'] ?? '');
                $rest = (string) preg_replace('/\{([A-Za-z]+)\}/', '', $text);

                if (str_contains($rest, '{') || str_contains($rest, '}')) {
                    $problems["rows.$r.cells.$c.text"] = 'Kurung kurawal hanya untuk isian, mis. {documentNumber}.';

                    continue;
                }

                preg_match_all('/\{([A-Za-z]+)\}/', $text, $found);

                foreach ($found[1] as $name) {
                    if (! in_array($name, $known, true)) {
                        $problems["rows.$r.cells.$c.text"] = "Isian {{$name}} tidak dikenal.";

                        continue 2;
                    }

                    $field = HeaderField::from($name);

                    if ($field->isReactive()) {
                        if (isset($reactiveSeen[$name]) || ! str_ends_with($text, '{'.$name.'}')) {
                            $problems["rows.$r.cells.$c.text"] = "{{$name}} hanya boleh sekali di seluruh kop dan harus di akhir teks sel.";

                            continue 2;
                        }

                        $reactiveSeen[$name] = true;
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * @return list<HeaderField>
     */
    private static function placeholderFields(): array
    {
        return array_values(array_filter(HeaderField::cases(), fn (HeaderField $field): bool => $field !== HeaderField::Text));
    }

    /**
     * Cleans a layout that passed validation: typed values, upper-case hex, text
     * without control characters or tags, widths of every row adding up to exactly
     * 100 (the last cell absorbs the difference), logo rows within the rows that exist.
     *
     * @param  array<string, mixed>  $input
     * @return Layout
     */
    public static function normalize(array $input): array
    {
        $rows = array_map(function (array $row): array {
            $cells = array_values($row['cells']);
            $widths = array_map(fn (array $cell): float => round((float) $cell['width'], 2), $cells);
            $widths[count($widths) - 1] = round(100 - array_sum(array_slice($widths, 0, -1)), 2);

            return [
                'height' => (int) $row['height'],
                'cells' => array_map(fn (array $cell, float $width): array => [
                    'width' => $width,
                    'text' => self::cleanText((string) ($cell['text'] ?? '')),
                    'bold' => (bool) ($cell['bold'] ?? false),
                    'italic' => (bool) ($cell['italic'] ?? false),
                    'uppercase' => (bool) ($cell['uppercase'] ?? false),
                    'align' => (string) $cell['align'],
                    'fontSize' => isset($cell['fontSize']) ? (int) $cell['fontSize'] : null,
                    'background' => isset($cell['background']) && $cell['background'] !== '' ? HeaderPalette::canonical((string) $cell['background']) : null,
                    'color' => HeaderPalette::canonical((string) $cell['color']),
                ], $cells, $widths),
            ];
        }, array_values($input['rows']));

        $logo = $input['logo'];

        return [
            'version' => self::VERSION,
            'border' => HeaderPalette::canonical((string) $input['border']),
            'logo' => [
                'enabled' => (bool) $logo['enabled'],
                'source' => (string) $logo['source'],
                'assetId' => isset($logo['assetId']) && $logo['assetId'] !== '' ? strtolower((string) $logo['assetId']) : null,
                'side' => (string) $logo['side'],
                'rows' => min((int) $logo['rows'], count($rows)),
                'width' => (int) $logo['width'],
                'valign' => (string) $logo['valign'],
                'padding' => (int) $logo['padding'],
            ],
            'rows' => $rows,
        ];
    }

    private static function cleanText(string $text): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', strip_tags($text)));
    }

    /**
     * Converts a version 1 layout (cells with `span`, `label`, `field` and a `fill`
     * name) to version 2 with the colours the engine drew for those names.
     *
     * @param  array<string, mixed>  $old
     * @return Layout
     */
    public static function fromV1(array $old): array
    {
        $rows = array_map(function (array $row): array {
            $cells = array_values($row['cells']);
            $total = array_sum(array_map(fn (array $cell): int => (int) $cell['span'], $cells)) ?: 1;

            return self::row((int) $row['height'], array_map(function (array $cell) use ($total): array {
                $label = trim((string) ($cell['label'] ?? ''));
                $field = (string) $cell['field'];
                $text = $field === 'text' ? $label : ($label === '' ? '{'.$field.'}' : $label.' : {'.$field.'}');
                $fill = (string) ($cell['fill'] ?? 'none');

                return self::cell($text, round((int) $cell['span'] / $total * 100, 2), [
                    'bold' => (bool) ($cell['bold'] ?? false),
                    'italic' => (bool) ($cell['italic'] ?? false),
                    'uppercase' => (bool) ($cell['uppercase'] ?? false),
                    'align' => (string) ($cell['align'] ?? 'left'),
                    'background' => match ($fill) {
                        'dark' => HeaderPalette::DARK,
                        'muted' => HeaderPalette::MUTED,
                        default => null,
                    },
                    'color' => $fill === 'dark' ? HeaderPalette::WHITE : HeaderPalette::TEXT,
                ]);
            }, $cells));
        }, array_values($old['rows']));

        $logo = $old['logo'] ?? ['enabled' => true, 'rows' => 4, 'width' => 22];

        return self::normalize([
            'border' => HeaderPalette::BORDER,
            'logo' => [
                'enabled' => (bool) $logo['enabled'], 'source' => 'company', 'assetId' => null, 'side' => 'left',
                'rows' => (int) $logo['rows'], 'width' => (int) $logo['width'], 'valign' => 'middle', 'padding' => 8,
            ],
            'rows' => $rows,
        ]);
    }

    /**
     * The layout in force: the stored one (converted when it is still version 1), or the default.
     *
     * @param  array<string, mixed>|null  $stored
     * @return Layout
     */
    public static function effective(?array $stored): array
    {
        if ($stored === null) {
            return self::default();
        }

        return ($stored['version'] ?? 1) >= self::VERSION ? self::normalize($stored) : self::fromV1($stored);
    }
}
