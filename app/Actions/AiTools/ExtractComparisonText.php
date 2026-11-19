<?php

namespace App\Actions\AiTools;

use App\Enums\AiTools\ExtractionMethod;
use App\Enums\AiTools\SourceKind;
use App\Models\AiTools\ComparisonSource;
use App\Models\Dcs\DocumentVersion;
use App\Support\Comparison\Extraction\ExtractionException;
use App\Support\Comparison\Extraction\OcrEngine;
use App\Support\Comparison\Extraction\PdfReader;
use Illuminate\Support\Facades\Storage;

/**
 * Gets the plain text of one side of a comparison. A PDF with a text layer is read directly
 * (exact, free); only pages with almost no text (scans) go to OCR. A DCS version is read from its
 * saved sections, pasted text is used as is.
 */
class ExtractComparisonText
{
    public function __construct(private readonly PdfReader $pdf, private readonly OcrEngine $ocr) {}

    /**
     * @throws ExtractionException
     */
    public function handle(ComparisonSource $source): string
    {
        [$text, $method, $pages] = match ($source->kind) {
            SourceKind::Paste => [(string) $source->pasted_text, ExtractionMethod::Pasted, null],
            SourceKind::DcsVersion => [$this->fromVersion($source), ExtractionMethod::Structured, null],
            SourceKind::Upload => $this->fromFile($source),
        };

        $text = $this->normalize($text);

        if (mb_strlen($text) < 20) {
            throw new ExtractionException("Dokumen {$source->side} tidak berisi teks yang dapat dibandingkan.");
        }

        $source->forceFill(['extracted_text' => $text, 'extraction_method' => $method, 'page_count' => $pages ?? $source->page_count])->save();

        return $text;
    }

    /**
     * @return array{string, ExtractionMethod, int}
     */
    private function fromFile(ComparisonSource $source): array
    {
        $disk = Storage::disk(config('ai.comparison.disk'));

        if ($source->file_path === null || ! $disk->exists($source->file_path)) {
            throw new ExtractionException("Berkas dokumen {$source->side} tidak ditemukan.");
        }

        $local = tempnam(sys_get_temp_dir(), 'cmp');

        try {
            file_put_contents($local, $disk->readStream($source->file_path));

            $count = $this->pdf->pageCount($local);

            if ($count > (int) config('ai.comparison.max_pages')) {
                throw new ExtractionException("Dokumen {$source->side} melebihi batas ".config('ai.comparison.max_pages').' halaman.');
            }

            $texts = $this->pdf->pageTexts($local);
            $scanned = [];

            for ($page = 1; $page <= $count; $page++) {
                if (mb_strlen(preg_replace('/\s+/u', '', $texts[$page - 1] ?? '') ?? '') < (int) config('ai.comparison.min_chars_per_page')) {
                    $scanned[] = $page;
                }
            }

            if ($scanned !== []) {
                $read = $this->ocr->read($this->pdf->renderPages($local, $scanned), $source->comparison);

                foreach ($scanned as $page) {
                    $texts[$page - 1] = $read[$page] ?? '';
                }
            }

            $method = match (true) {
                $scanned === [] => ExtractionMethod::TextLayer,
                count($scanned) === $count => ExtractionMethod::Ocr,
                default => ExtractionMethod::Mixed,
            };

            return [implode("\n\n", array_slice($texts, 0, $count)), $method, $count];
        } finally {
            @unlink($local);
        }
    }

    private function fromVersion(ComparisonSource $source): string
    {
        /** @var DocumentVersion|null $version */
        $version = $source->dcsVersion()->with(['content', 'sections'])->first();

        if ($version === null) {
            throw new ExtractionException("Versi dokumen {$source->side} sudah tidak tersedia.");
        }

        $blocks = [];

        foreach ([['Tujuan', $version->content?->purpose], ['Ruang Lingkup', $version->content?->scope]] as [$title, $body]) {
            if (filled($body)) {
                $blocks[] = $title."\n".$this->plain((string) $body);
            }
        }

        foreach ($version->sections->sortBy('position')->values() as $index => $section) {
            $block = ($index + 1).'. '.mb_strtoupper(trim($section->heading))."\n".$this->plain($section->content);

            foreach ($section->table_data['rows'] ?? [] as $row) {
                $block .= "\n".implode(' | ', $row);
            }

            $blocks[] = $block;
        }

        return implode("\n\n", $blocks);
    }

    private function plain(string $html): string
    {
        $html = preg_replace('#</(p|div|li|h[1-6])>|<br\s*/?>#i', "\n", $html) ?? $html;

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\n{3,}/', "\n\n", $text) ?? $text);
    }
}
