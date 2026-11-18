<?php

use App\Support\Dcs\NumberingMask;

$tokens = ['DocType' => 'SOP', 'Department' => 'HRD', 'Year' => 2026, 'Month' => 7, 'Day' => 5];

it('renders the default mask with a padded sequence', function () use ($tokens) {
    expect(NumberingMask::render(NumberingMask::DEFAULT_MASK, $tokens, 7))->toBe('SOP/HRD/2026/0007');
});

it('renders sequences, Roman months and plain tokens', function (string $mask, string $expected) use ($tokens) {
    expect(NumberingMask::render($mask, $tokens, 12))->toBe($expected);
})->with([
    'unpadded sequence' => ['{Sequence}/{DocType}', '12/SOP'],
    'padded to 3' => ['{Sequence:3}/{DocType}', '012/SOP'],
    'roman month' => ['{Sequence:3}/{MonthRoman}/{Year}', '012/VII/2026'],
    'month and day' => ['{Day}-{Month}-{Year}-{Sequence}', '5-7-2026-12'],
    'sequence wider than padding' => ['{Sequence:1}', '12'],
]);

it('wraps the Roman month around an invalid month', function () {
    expect(NumberingMask::render('{MonthRoman}-{Sequence}', ['Month' => 13], 1))->toBe('I-1')
        ->and(NumberingMask::render('{MonthRoman}-{Sequence}', ['Month' => 12], 1))->toBe('XII-1');
});

it('drops an empty token and collapses the separators around it', function () {
    expect(NumberingMask::render('{DocType}/{Department}/{Year}/{Sequence:4}', ['DocType' => 'SOP', 'Department' => '', 'Year' => 2026], 3))->toBe('SOP/2026/0003')
        ->and(NumberingMask::render('{Department}/{DocType}/{Sequence}', ['DocType' => 'SOP', 'Department' => ''], 3))->toBe('SOP/3')
        ->and(NumberingMask::render('{DocType}-{Department}', ['DocType' => 'SOP', 'Department' => ''], 3))->toBe('SOP');
});

it('keeps deliberate double separators when no token is empty', function () {
    expect(NumberingMask::render('{DocType}//{Sequence}', ['DocType' => 'SOP'], 1))->toBe('SOP//1');
});

it('refuses a token without a value', function () {
    NumberingMask::render('{DocType}/{Nope}/{Sequence}', ['DocType' => 'SOP'], 1);
})->throws(InvalidArgumentException::class, 'Mask token "{Nope}" has no value');

it('builds the scope hash from the scope tokens in their declared order', function () use ($tokens) {
    expect(NumberingMask::scopeHash(['DocType', 'Department', 'Year'], $tokens))->toBe('SOP|HRD|2026')
        ->and(NumberingMask::scopeHash(['Year', 'DocType'], $tokens))->toBe('2026|SOP');
});

it('refuses a scope token without a value', function () {
    NumberingMask::scopeHash(['Year'], []);
})->throws(InvalidArgumentException::class);
