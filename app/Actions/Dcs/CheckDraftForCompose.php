<?php

namespace App\Actions\Dcs;

use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\VersionContent;
use App\Support\Dcs\Pdf\PdfEngineException;
use App\Support\Dcs\Pdf\Php\Fonts\StandardFont;

/**
 * Looks through a draft's content before the PDF is drawn and lists what is wrong in words an
 * author can act on: where it is (step and item) and how to fix it. The engine would otherwise
 * stop with a generic failure, or print an empty box.
 */
class CheckDraftForCompose
{
    /**
     * @return list<string> empty when the draft can be composed
     */
    public function handle(DocumentVersion $version, VersionContent $content): array
    {
        $problems = [];
        $texts = ['Judul dokumen' => [$version->document->title], 'Tujuan' => [(string) $content->purpose], 'Ruang lingkup' => [(string) $content->scope]];

        foreach ($version->sections()->get() as $index => $section) {
            $where = 'Isi dokumen, bagian '.($index + 1);
            $hasText = trim($section->content) !== '';
            $hasTable = $section->table_data !== null;

            if (trim($section->heading) === '' && ($hasText || $hasTable)) {
                $problems[] = "{$where}: judul belum diisi. Isi judulnya, atau hapus bagian ini.";
            }

            $texts[$where] = [$section->heading, $section->content, ...$this->tableTexts($section->table_data)];
        }

        foreach ($content->flow_mode === 'builder' ? $content->flow_steps : [] as $step) {
            $label = config('dcs.flow_symbol_labels.'.($step['type'] ?? ''), 'Langkah');
            $where = 'Diagram alir, langkah '.($step['order'] ?? '?')." ({$label})";

            if (trim((string) ($step['text'] ?? '')) === '') {
                $problems[] = "{$where}: belum diberi teks. Klik langkahnya lalu ketik teksnya, atau hapus langkah itu.";
            }

            $texts[$where] = [(string) ($step['text'] ?? ''), (string) ($step['actor'] ?? '')];
        }

        $texts['Diagram alir, nama jalur'] = array_map('strval', $content->flow_lanes ?? []);

        foreach ($version->attachments()->get() as $index => $attachment) {
            $where = 'Lampiran '.($index + 1);

            if (trim($attachment->label) === '' || trim($attachment->url) === '') {
                $problems[] = "{$where}: nama dan alamat lampiran harus diisi. Lengkapi, atau hapus lampiran itu.";
            }

            $texts[$where] = [$attachment->label, $attachment->url];
        }

        return [...$problems, ...$this->unprintable($texts)];
    }

    /**
     * @param  array{columns?: array<int, mixed>, rows?: array<int, array<int, mixed>>}|null  $table
     * @return list<string>
     */
    private function tableTexts(?array $table): array
    {
        return $table === null ? [] : array_map('strval', [...($table['columns'] ?? []), ...array_merge(...array_map('array_values', $table['rows'] ?? []))]);
    }

    /**
     * The PDF fonts hold Western European letters and common punctuation only; any other symbol
     * (arrows, check marks, emoji, other scripts) cannot be printed.
     *
     * @param  array<string, list<string>>  $texts  where => the texts found there
     * @return list<string>
     */
    private function unprintable(array $texts): array
    {
        $font = StandardFont::named('Helvetica');
        $problems = [];

        foreach ($texts as $where => $parts) {
            $bad = [];

            foreach (array_unique(mb_str_split(implode(' ', $parts), 1, 'UTF-8')) as $char) {
                if (trim($char) === '') {
                    continue;
                }

                try {
                    $font->widthOfTextAtSize($char, 10);
                } catch (PdfEngineException) {
                    $bad[] = $char;
                }
            }

            if ($bad !== []) {
                $problems[] = "{$where}: karakter \"".implode('", "', array_slice($bad, 0, 5)).'" tidak bisa dicetak ke PDF. Ganti dengan huruf atau tanda baca biasa.';
            }
        }

        return $problems;
    }
}
