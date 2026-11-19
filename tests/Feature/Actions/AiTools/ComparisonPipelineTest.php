<?php

use App\Enums\AiTools\ChangeKind;
use App\Enums\AiTools\ComparisonStage;
use App\Enums\AiTools\ComparisonStatus;
use App\Enums\AiTools\SourceKind;
use App\Jobs\AiTools\RunComparisonStage;
use App\Models\AiCall;
use App\Models\AiTools\DocumentComparison;
use App\Support\Comparison\Extraction\ExtractionException;
use Tests\Support\FakeLlmClient;

function seedComparison(string $a, string $b): DocumentComparison
{
    $comparison = DocumentComparison::factory()->create();
    $comparison->sources()->create(['side' => 'a', 'kind' => SourceKind::Paste, 'pasted_text' => $a]);
    $comparison->sources()->create(['side' => 'b', 'kind' => SourceKind::Paste, 'pasted_text' => $b]);

    return $comparison;
}

function runStages(DocumentComparison $comparison, ComparisonStage ...$stages): void
{
    foreach ($stages as $stage) {
        (new RunComparisonStage($comparison->id, $stage))->handle();
    }
}

$docA = "Pasal 1\nPengusaha wajib membayar upah pekerja setiap bulan tepat waktu.\nPasal 2\nPekerja berhak atas cuti tahunan selama dua belas hari kerja.\nPasal 3\nPemutusan hubungan kerja karena kesalahan berat dapat dilakukan langsung.";
$docB = "Pasal 1\nPengusaha wajib membayar upah pekerja setiap minggu tepat waktu.\nPasal 2\nPekerja berhak atas cuti tahunan selama dua belas hari kerja.\nPasal 4\nPenyelesaian perselisihan hubungan industrial dilakukan melalui bipartit terlebih dahulu.";

it('builds the matrix: diff in code, labels from the model', function () use ($docA, $docB) {
    $fake = FakeLlmClient::install();
    $comparison = seedComparison($docA, $docB);

    runStages($comparison, ComparisonStage::Extracting, ComparisonStage::Splitting);
    $fake->reply(['pairs' => []]);
    runStages($comparison, ComparisonStage::Aligning, ComparisonStage::Diffing);

    $rows = $comparison->rows()->orderBy('ordinal')->get();

    expect($rows->pluck('change_kind')->all())->toBe([ChangeKind::Changed, ChangeKind::Unchanged, ChangeKind::Added, ChangeKind::Removed]);

    $changed = $rows[0]->diff;
    expect(collect($changed['a'])->firstWhere('o', 'del')['t'])->toContain('bulan')
        ->and(collect($changed['b'])->firstWhere('o', 'ins')['t'])->toContain('minggu');

    $fake->reply(fn ($request) => ['rows' => [
        ['id' => 1, 'label' => 'diubah', 'explanation' => 'Periode pembayaran upah berubah dari bulanan menjadi mingguan.'],
        ['id' => 3, 'label' => 'norma_baru', 'explanation' => 'Penyelesaian bipartit diwajibkan.'],
        ['id' => 4, 'label' => 'dihapus_total', 'explanation' => 'Ketentuan PHK langsung dihapus.'],
    ]]);
    runStages($comparison, ComparisonStage::Analyzing);

    $fake->reply(['pokok_perubahan' => 'Perlindungan upah diperkuat.', 'norma_baru_terpenting' => [['judul' => 'Bipartit', 'pasal' => ['Pasal 4'], 'uraian' => 'Wajib bipartit.']], 'dihapus_penting' => [], 'dampak_risiko' => ['Biaya payroll naik.'], 'rekomendasi' => ['Sesuaikan SOP.']]);
    runStages($comparison, ComparisonStage::Summarizing);

    $comparison->refresh();

    expect($comparison->status)->toBe(ComparisonStatus::Completed)
        ->and($comparison->progress)->toBe(100)
        ->and($comparison->summary['counts'])->toEqual(['added' => 1, 'removed' => 1, 'changed' => 1, 'unchanged' => 1])
        ->and($comparison->summary['ketentuan_tetap'])->toBe([['a' => 'Pasal 2', 'b' => 'Pasal 2']])
        ->and($comparison->summary['norma_baru_terpenting'][0]['pasal'])->toBe(['Pasal 4'])
        ->and(AiCall::where('comparison_id', $comparison->id)->count())->toBe(3);
});

it('lets the model pair a renumbered article but only with ids it was given', function () {
    $fake = FakeLlmClient::install()->reply(['pairs' => [['a' => 'A9', 'b' => 'B1']]])->reply(['pairs' => [['a' => 'A2', 'b' => 'B2']]]);
    $comparison = seedComparison(
        "Pasal 1\nUpah dibayar bulanan oleh pengusaha.\nPasal 2\nPekerja kontrak tidak mendapat pesangon sama sekali saat kontrak selesai.",
        "Pasal 7\nAturan jaminan kecelakaan kerja ditanggung perusahaan.\nPasal 8\nPekerja kontrak memperoleh uang kompensasi setelah kontrak berakhir.",
    );

    runStages($comparison, ComparisonStage::Extracting, ComparisonStage::Splitting, ComparisonStage::Aligning);

    $paired = $comparison->rows()->whereNotNull('article_a_id')->whereNotNull('article_b_id')->get();

    expect($fake->requests)->toHaveCount(2)
        ->and($fake->requests[1]->prompt)->toContain('ditolak karena')
        ->and($paired)->toHaveCount(1)
        ->and($paired[0]->pairing->value)->toBe('llm');
});

it('rejects a label that does not fit the row and retries once', function () {
    $fake = FakeLlmClient::install();
    $comparison = seedComparison("Pasal 1\nTeks lama yang cukup panjang untuk dibandingkan.", "Pasal 1\nTeks baru yang cukup panjang untuk dibandingkan.\nPasal 2\nKetentuan tambahan yang benar-benar baru.");
    runStages($comparison, ComparisonStage::Extracting, ComparisonStage::Splitting, ComparisonStage::Aligning, ComparisonStage::Diffing);

    $fake->reply(['rows' => [['id' => 1, 'label' => 'diubah', 'explanation' => 'ok'], ['id' => 2, 'label' => 'diubah', 'explanation' => 'salah label']]])
        ->reply(['rows' => [['id' => 1, 'label' => 'tetap', 'explanation' => 'Hanya redaksional.'], ['id' => 2, 'label' => 'norma_baru', 'explanation' => 'Ketentuan baru.']]]);
    runStages($comparison, ComparisonStage::Analyzing);

    expect($comparison->rows()->orderBy('ordinal')->pluck('change_kind')->all())->toBe([ChangeKind::Unchanged, ChangeKind::Added])
        ->and(AiCall::where('ok', false)->count())->toBe(1);
});

it('fails with a clear message when the model answers invalid JSON twice', function () {
    $fake = FakeLlmClient::install();
    $comparison = seedComparison("Pasal 1\nTeks lama yang cukup panjang untuk dibandingkan.", "Pasal 1\nTeks baru yang cukup panjang untuk dibandingkan.");
    runStages($comparison, ComparisonStage::Extracting, ComparisonStage::Splitting, ComparisonStage::Aligning, ComparisonStage::Diffing);
    $fake->reply('bukan json')->reply('{"rows": "salah"}');

    $job = new RunComparisonStage($comparison->id, ComparisonStage::Analyzing);

    try {
        $job->handle();
    } catch (Throwable $e) {
        $job->failed($e);
    }

    $comparison->refresh();

    expect($comparison->status)->toBe(ComparisonStatus::Failed)
        ->and($comparison->error_message)->toContain('tidak sesuai format')->toContain('Menganalisis');
});

it('marks the comparison failed when a document has no text', function () {
    FakeLlmClient::install();
    $comparison = seedComparison('   ', 'Pasal 1 teks yang cukup panjang.');
    $job = new RunComparisonStage($comparison->id, ComparisonStage::Extracting);

    expect(fn () => $job->handle())->toThrow(ExtractionException::class);

    $job->failed(new ExtractionException('Dokumen a tidak berisi teks yang dapat dibandingkan.'));

    expect($comparison->fresh()->status)->toBe(ComparisonStatus::Failed);
});
