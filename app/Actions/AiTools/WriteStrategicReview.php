<?php

namespace App\Actions\AiTools;

use App\Enums\AiTools\ChangeKind;
use App\Models\AiTools\ComparisonArticle;
use App\Models\AiTools\ComparisonRow;
use App\Models\AiTools\DocumentComparison;
use App\Support\Ai\LlmRequest;
use App\Support\Ai\StructuredGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the summary under the matrix. "Ketentuan yang substansinya tetap" and the counts come
 * from the rows in code; the model only writes the strategic review, from the rows' explanations,
 * and may cite only article numbers that exist in the documents.
 */
class WriteStrategicReview
{
    private const MAX_ROWS = 300;

    public function __construct(private readonly StructuredGenerator $generator) {}

    public function handle(DocumentComparison $comparison): void
    {
        $rows = $comparison->rows()->with(['articleA', 'articleB'])->orderBy('ordinal')->get();
        $unchanged = $rows->where('change_kind', ChangeKind::Unchanged);
        $differing = $rows->where('change_kind', '!=', ChangeKind::Unchanged);

        $summary = [
            'counts' => collect(ChangeKind::cases())->mapWithKeys(fn (ChangeKind $kind): array => [$kind->value => $rows->where('change_kind', $kind)->count()])->all(),
            'ketentuan_tetap' => $unchanged->map(fn (ComparisonRow $row): array => [
                'a' => $this->title($row->articleA),
                'b' => $this->title($row->articleB),
            ])->values()->all(),
            ...($differing->isEmpty() ? $this->nothingChanged() : $this->review($comparison, $rows, $differing)),
        ];

        $comparison->forceFill(['summary' => $summary])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function nothingChanged(): array
    {
        return ['pokok_perubahan' => 'Tidak ditemukan perbedaan substansi antara kedua dokumen.', 'norma_baru_terpenting' => [], 'dihapus_penting' => [], 'dampak_risiko' => [], 'rekomendasi' => []];
    }

    /**
     * @param  Collection<int, ComparisonRow>  $all
     * @param  Collection<int, ComparisonRow>  $differing
     * @return array<string, mixed>
     */
    private function review(DocumentComparison $comparison, $all, $differing): array
    {
        $items = $differing->sortBy(fn (ComparisonRow $row): int => $row->change_kind->priority() * 100000 + $row->ordinal)->take(self::MAX_ROWS)->map(fn (ComparisonRow $row): array => [
            'jenis' => $row->change_kind->label(),
            'pasal_a' => $row->articleA?->number_label,
            'pasal_b' => $row->articleB?->number_label,
            'analisis' => Str::limit((string) $row->explanation, 260, '…'),
        ])->values()->all();

        $known = $all->flatMap(fn (ComparisonRow $row): array => [$row->articleA?->number_label, $row->articleB?->number_label])
            ->filter()->map(fn (string $label): string => $this->normalize($label))->unique()->all();

        $prompt = 'Dokumen A (lama): "'.$comparison->label_a.'"; Dokumen B (baru): "'.$comparison->label_b."\".\n"
            .'Berikut hasil perbandingan per pasal yang berbeda:'."\n".json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n"
            .'Susun Tinjauan Strategis berbahasa Indonesia hanya berdasarkan data di atas. Balas HANYA JSON dengan bentuk: '
            .'{"pokok_perubahan":"1-3 kalimat arah perubahan","norma_baru_terpenting":[{"judul":"...","pasal":["Pasal 5"],"uraian":"..."}],'
            .'"dihapus_penting":[{"judul":"...","pasal":["Pasal 9"],"uraian":"..."}],"dampak_risiko":["..."],"rekomendasi":["..."]}. '
            .'Setiap entri "pasal" harus berupa nomor pasal yang tertulis pada data. Maksimal 8 entri per daftar; daftar boleh kosong.';

        $entry = ['judul' => ['required', 'string', 'max:300'], 'pasal' => ['present', 'array'], 'pasal.*' => ['string'], 'uraian' => ['required', 'string', 'max:1500']];

        $reply = $this->generator->generate('strategic', new LlmRequest($prompt, 'Anda konsultan hukum senior yang menulis tinjauan strategis perubahan dokumen hukum.'), [
            'pokok_perubahan' => ['required', 'string', 'max:2000'],
            'norma_baru_terpenting' => ['present', 'array', 'max:8'],
            'norma_baru_terpenting.*.judul' => $entry['judul'],
            'norma_baru_terpenting.*.pasal' => $entry['pasal'],
            'norma_baru_terpenting.*.pasal.*' => $entry['pasal.*'],
            'norma_baru_terpenting.*.uraian' => $entry['uraian'],
            'dihapus_penting' => ['present', 'array', 'max:8'],
            'dihapus_penting.*.judul' => $entry['judul'],
            'dihapus_penting.*.pasal' => $entry['pasal'],
            'dihapus_penting.*.pasal.*' => $entry['pasal.*'],
            'dihapus_penting.*.uraian' => $entry['uraian'],
            'dampak_risiko' => ['present', 'array', 'max:8'],
            'dampak_risiko.*' => ['string', 'max:800'],
            'rekomendasi' => ['present', 'array', 'max:8'],
            'rekomendasi.*' => ['string', 'max:800'],
        ], $comparison, function (array $data) use ($known): array {
            if ($known === []) {
                return [];
            }

            $problems = [];

            foreach (['norma_baru_terpenting', 'dihapus_penting'] as $list) {
                foreach ($data[$list] as $item) {
                    foreach ($item['pasal'] as $ref) {
                        if (! in_array($this->normalize($ref), $known, true)) {
                            $problems[] = "Rujukan \"{$ref}\" tidak ada pada data.";
                        }
                    }
                }
            }

            return $problems;
        });

        return array_intersect_key($reply, array_flip(['pokok_perubahan', 'norma_baru_terpenting', 'dihapus_penting', 'dampak_risiko', 'rekomendasi']));
    }

    private function title(?ComparisonArticle $article): ?string
    {
        return $article === null ? null : ($article->number_label ?? $article->heading);
    }

    private function normalize(string $label): string
    {
        return mb_strtolower(preg_replace('/\s+/u', '', $label) ?? $label);
    }
}
