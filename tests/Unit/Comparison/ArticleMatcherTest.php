<?php

use App\Enums\AiTools\Pairing;
use App\Support\Comparison\ArticleMatcher;

function article(int $ordinal, ?string $label, string $body, ?string $heading = null): array
{
    return ['ordinal' => $ordinal, 'number_label' => $label, 'heading' => $heading, 'body' => $body];
}

it('pairs the same number with changed text', function () {
    $result = (new ArticleMatcher)->match(
        [article(1, 'Pasal 1', 'Pengusaha wajib membayar upah pekerja setiap bulan tepat waktu')],
        [article(1, 'Pasal 1', 'Pengusaha wajib membayar upah pekerja setiap minggu tepat waktu')],
    );

    expect($result['pairs'])->toBe([['a' => 1, 'b' => 1, 'pairing' => Pairing::Number]]);
});

it('pairs a renumbered article by its text', function () {
    $text = 'Pekerja berhak atas cuti tahunan selama dua belas hari kerja setelah satu tahun bekerja';
    $result = (new ArticleMatcher)->match([article(1, 'Pasal 79', $text)], [article(1, 'Pasal 81', $text.' penuh')]);

    expect($result['pairs'][0]['pairing'])->toBe(Pairing::Similar);
});

it('leaves unrelated articles unmatched and never uses one twice', function () {
    $result = (new ArticleMatcher)->match(
        [article(1, 'Pasal 1', 'upah pekerja dibayar setiap bulan oleh pengusaha'), article(2, 'Pasal 2', 'pemutusan hubungan kerja karena kesalahan berat')],
        [article(1, 'Pasal 9', 'penyelesaian perselisihan melalui musyawarah mufakat')],
    );

    expect($result['pairs'])->toBe([])->and($result['unmatched_a'])->toBe([1, 2])->and($result['unmatched_b'])->toBe([1]);
});

it('matches BAB headings by number even when the title changed', function () {
    $result = (new ArticleMatcher)->match([article(1, 'BAB I', 'KETENTUAN UMUM')], [article(1, 'BAB I', 'DEFINISI')]);

    expect($result['pairs'])->toHaveCount(1);
});

it('arranges rows like A and places B-only articles after their predecessor', function () {
    $rows = (new ArticleMatcher)->arrange(
        [['a' => 1, 'b' => 1, 'pairing' => Pairing::Number], ['a' => 3, 'b' => 3, 'pairing' => Pairing::Number]],
        [1, 2, 3],
        [1, 2, 3, 4],
    );

    expect(array_map(fn ($r) => [$r['a'], $r['b']], $rows))->toBe([[1, 1], [null, 2], [2, null], [3, 3], [null, 4]])
        ->and(array_map(fn ($r) => [$r['a'], $r['b']], (new ArticleMatcher)->arrange([['a' => 1, 'b' => 2, 'pairing' => Pairing::Number]], [1], [1, 2])))->toBe([[null, 1], [1, 2]]);
});
