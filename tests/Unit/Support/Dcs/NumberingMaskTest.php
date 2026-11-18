<?php

use App\Support\Dcs\NumberingMask;

it('accepts valid masks', function (string $mask) {
    expect(NumberingMask::validate($mask))->toBeNull();
})->with([
    'default' => [NumberingMask::DEFAULT_MASK],
    'bare sequence' => ['{Sequence}'],
    'roman month' => ['{Sequence:3}/{DocType}/{MonthRoman}/{Year}'],
    'repeated token' => ['{Year}-{Year}-{Sequence}'],
]);

it('explains why a mask is rejected', function (?string $mask, string $message) {
    expect(NumberingMask::validate($mask))->toBe($message);
})->with([
    'null' => [null, 'Format nomor tidak boleh kosong.'],
    'blank' => [' ', 'Format nomor tidak boleh kosong.'],
    'without sequence' => ['{DocType}', 'Format nomor wajib memuat token {Sequence} atau {Sequence:N}.'],
    'unknown token' => ['{Sequence}/{Bogus}', 'Token "{Bogus}" tidak dikenal. Token yang tersedia: {DocType}, {Department}, {Year}, {Month}, {MonthRoman}, {Day}, {Sequence}.'],
]);
