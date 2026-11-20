<?php

use App\Support\Templates\Placeholders;

it('reads valid placeholders once each, in order of first use', function () {
    expect(Placeholders::codes('A {{NamaPerusahaan}} b {{Mata_Uang1}} c {{NamaPerusahaan}}'))->toBe(['NamaPerusahaan', 'Mata_Uang1'])
        ->and(Placeholders::invalidTokens('A {{NamaPerusahaan}} b'))->toBe([])
        ->and(Placeholders::hasStrayBraces('A {{NamaPerusahaan}} b'))->toBeFalse();
});

it('treats single braces and plain text as plain text', function () {
    expect(Placeholders::codes('Harga {1} dan {x}, tanpa tanda.'))->toBe([])
        ->and(Placeholders::invalidTokens('Harga {1} dan {x}'))->toBe([])
        ->and(Placeholders::hasStrayBraces('Harga {1} dan {x}'))->toBeFalse();
});

it('flags every malformed or dangerous brace group, so none is read as a field', function (string $text) {
    expect(Placeholders::invalidTokens($text) !== [] || Placeholders::hasStrayBraces($text))->toBeTrue();
})->with([
    'spaces inside' => ['{{ NamaPerusahaan }}'],
    'dunder name' => ['{{__class__}}'],
    'arithmetic' => ['{{7*7}}'],
    'php variable' => ['{{ $x }}'],
    'filter pipe' => ['{{a|upper}}'],
    'quoted' => ["{{'x'}}"],
    'call' => ['{{system("ls")}}'],
    'dotted path' => ['{{NamaPerusahaan.x}}'],
    'empty' => ['{{}}'],
    'nested' => ['{{{{Kode}}}}'],
    'extra closing' => ['{{Kode}}}}'],
    'unclosed' => ['{{Kode'],
    'lone closing' => ['}}'],
    'lone opening' => ['{{'],
    'too long' => ['{{'.str_repeat('a', 61).'}}'],
    'newline inside' => ["{{Nama\n}}"],
    'null byte' => ["{{Nama\0}}"],
    'cyrillic lookalike' => ["{{N\u{0430}ma}}"],
    'digit first' => ['{{1Nama}}'],
    'html mixed in' => ['<img src=x onerror="{{ alert(1) }}">'],
]);

it('keeps a sixty character code and refuses sixty one', function () {
    $sixty = str_repeat('a', 60);

    expect(Placeholders::codes('{{'.$sixty.'}}'))->toBe([$sixty])
        ->and(Placeholders::invalidTokens('{{'.str_repeat('a', 61).'}}'))->not->toBe([]);
});
