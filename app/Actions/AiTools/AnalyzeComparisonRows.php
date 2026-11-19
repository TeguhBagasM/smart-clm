<?php

namespace App\Actions\AiTools;

use App\Enums\AiTools\ChangeKind;
use App\Models\AiTools\ComparisonRow;
use App\Models\AiTools\DocumentComparison;
use App\Support\Ai\LlmRequest;
use App\Support\Ai\StructuredGenerator;
use Illuminate\Support\Str;

/**
 * Writes the "Analisis Perubahan" column. The model sees only rows that differ, in batches sized by
 * characters, and must answer one entry per row it was given with a label that fits the row: it
 * cannot turn an added article into a changed one. It may call a changed row "tetap" when the
 * difference is only wording.
 */
class AnalyzeComparisonRows
{
    private const TEXT_LIMIT = 2500;

    private const LABELS = ['diubah', 'tetap', 'norma_baru', 'dihapus_total'];

    public function __construct(private readonly StructuredGenerator $generator) {}

    public function handle(DocumentComparison $comparison): void
    {
        $rows = $comparison->rows()->with(['articleA', 'articleB'])->whereIn('change_kind', [ChangeKind::Changed, ChangeKind::Added, ChangeKind::Removed])->orderBy('ordinal')->get();

        foreach ($this->batches(array_values($rows->all())) as $batch) {
            $this->analyze($comparison, $batch);
        }
    }

    /**
     * @param  list<ComparisonRow>  $rows
     * @return list<list<ComparisonRow>>
     */
    private function batches(array $rows): array
    {
        $batches = [];
        $current = [];
        $size = 0;

        foreach ($rows as $row) {
            $length = min(self::TEXT_LIMIT, mb_strlen((string) $row->articleA?->body)) + min(self::TEXT_LIMIT, mb_strlen((string) $row->articleB?->body));

            if ($current !== [] && $size + $length > (int) config('ai.comparison.analysis_batch_chars')) {
                $batches[] = $current;
                $current = [];
                $size = 0;
            }

            $current[] = $row;
            $size += $length;
        }

        if ($current !== []) {
            $batches[] = $current;
        }

        return $batches;
    }

    /**
     * @param  list<ComparisonRow>  $batch
     */
    private function analyze(DocumentComparison $comparison, array $batch): void
    {
        $payload = array_map(fn (ComparisonRow $row): array => [
            'id' => $row->ordinal,
            'jenis' => match ($row->change_kind) {
                ChangeKind::Added => 'baru',
                ChangeKind::Removed => 'dihapus',
                default => 'diubah',
            },
            'pasal_a' => $row->articleA?->number_label,
            'isi_a' => $row->articleA === null ? null : Str::limit($row->articleA->body, self::TEXT_LIMIT, '…'),
            'pasal_b' => $row->articleB?->number_label,
            'isi_b' => $row->articleB === null ? null : Str::limit($row->articleB->body, self::TEXT_LIMIT, '…'),
        ], $batch);

        $prompt = 'Dokumen A (lama): "'.$comparison->label_a.'"; Dokumen B (baru): "'.$comparison->label_b."\".\n\n"
            .json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n"
            .'Untuk SETIAP baris, tulis analisis 1-3 kalimat Bahasa Indonesia tentang substansi perubahannya (hak, kewajiban, angka, tenggat, sanksi, subjek) dan pilih label: '
            .'"norma_baru" untuk jenis baru; "dihapus_total" untuk jenis dihapus; untuk jenis diubah pilih "diubah" atau "tetap" (hanya bila perbedaannya murni redaksional, substansi sama). '
            .'Jangan mengarang isi yang tidak ada di teks. Balas HANYA JSON: {"rows":[{"id":1,"label":"diubah","explanation":"..."}]} dengan satu entri per id.';

        $ids = array_map(fn (ComparisonRow $row): int => $row->ordinal, $batch);
        $kinds = collect($batch)->mapWithKeys(fn (ComparisonRow $row): array => [$row->ordinal => $row->change_kind]);

        $reply = $this->generator->generate('analyze', new LlmRequest($prompt, 'Anda analis hukum Indonesia yang menjelaskan perubahan antar dokumen hukum secara ringkas dan akurat.'), [
            'rows' => ['required', 'array'],
            'rows.*.id' => ['required', 'integer'],
            'rows.*.label' => ['required', 'in:'.implode(',', self::LABELS)],
            'rows.*.explanation' => ['required', 'string', 'max:1200'],
        ], $comparison, function (array $data) use ($ids, $kinds): array {
            $given = array_column($data['rows'], 'id');
            $problems = [];

            if (array_diff($ids, $given) !== [] || array_diff($given, $ids) !== [] || count($given) !== count(array_unique($given))) {
                $problems[] = 'Daftar id harus persis sama dengan yang diberikan, tanpa duplikat: '.implode(',', $ids).'.';
            }

            foreach ($data['rows'] as $row) {
                $kind = $kinds[$row['id']] ?? null;
                $allowed = match ($kind) {
                    ChangeKind::Added => ['norma_baru'],
                    ChangeKind::Removed => ['dihapus_total'],
                    default => ['diubah', 'tetap'],
                };

                if ($kind !== null && ! in_array($row['label'], $allowed, true)) {
                    $problems[] = "Label baris {$row['id']} harus salah satu dari: ".implode(', ', $allowed).'.';
                }
            }

            return $problems;
        });

        foreach ($reply['rows'] as $entry) {
            $row = collect($batch)->firstWhere('ordinal', $entry['id']);
            $row->forceFill([
                'explanation' => trim($entry['explanation']),
                'change_kind' => $entry['label'] === 'tetap' ? ChangeKind::Unchanged : $row->change_kind,
            ])->save();
        }
    }
}
