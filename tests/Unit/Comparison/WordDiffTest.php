<?php

use App\Support\Comparison\WordDiff;

it('marks removed words on side A and added words on side B', function () {
    $diff = (new WordDiff)->diff(
        'Pengusaha wajib membayar upah setiap bulan.',
        'Pengusaha wajib membayar upah setiap minggu dan tepat waktu.',
    );

    expect($diff['a'])->toBe([
        ['t' => 'Pengusaha wajib membayar upah setiap', 'o' => 'eq'],
        ['t' => 'bulan.', 'o' => 'del'],
    ])->and($diff['b'])->toBe([
        ['t' => 'Pengusaha wajib membayar upah setiap', 'o' => 'eq'],
        ['t' => 'minggu dan tepat waktu.', 'o' => 'ins'],
    ]);
});

it('keeps unchanged words around a change in the middle', function () {
    $diff = (new WordDiff)->diff('a b c d e', 'a b X d e');

    expect($diff['a'])->toBe([['t' => 'a b', 'o' => 'eq'], ['t' => 'c', 'o' => 'del'], ['t' => 'd e', 'o' => 'eq']])
        ->and($diff['b'])->toBe([['t' => 'a b', 'o' => 'eq'], ['t' => 'X', 'o' => 'ins'], ['t' => 'd e', 'o' => 'eq']]);
});

it('ignores whitespace and line breaks but not case or punctuation', function () {
    $diff = new WordDiff;

    expect($diff->same("satu  dua\ntiga", 'satu dua tiga'))->toBeTrue()
        ->and($diff->same('satu dua', 'Satu dua'))->toBeFalse()
        ->and($diff->same('satu dua.', 'satu dua'))->toBeFalse();
});

it('shows a whole side as removed when the other is empty', function () {
    $diff = (new WordDiff)->diff('hanya di A', '');

    expect($diff['a'])->toBe([['t' => 'hanya di A', 'o' => 'del']])->and($diff['b'])->toBe([]);
});

it('falls back to one removal and one addition when the texts are huge', function () {
    $a = implode(' ', array_map(fn ($i) => "a{$i}", range(1, 2600)));
    $b = implode(' ', array_map(fn ($i) => "b{$i}", range(1, 2600)));

    $diff = (new WordDiff)->diff($a, $b);

    expect($diff['a'])->toHaveCount(1)->and($diff['a'][0]['o'])->toBe('del')->and($diff['b'][0]['o'])->toBe('ins');
});
