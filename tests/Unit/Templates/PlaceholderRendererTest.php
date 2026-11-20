<?php

use App\Support\Templates\PlaceholderRenderer;

it('cuts a text into plain parts and fields, flagging a field without a value as missing', function () {
    $segments = PlaceholderRenderer::segments('Sebesar {{Nilai}} {{Mata}} saja.', ['Nilai' => '1.000']);

    expect($segments)->toBe([
        ['text' => 'Sebesar '],
        ['code' => 'Nilai', 'value' => '1.000', 'missing' => false],
        ['text' => ' '],
        ['code' => 'Mata', 'value' => '[Mata]', 'missing' => true],
        ['text' => ' saja.'],
    ]);
});

it('never reads a value again: placeholders and markup in a value stay as typed', function () {
    $values = ['Nama' => '{{Rahasia}} <script>alert(1)</script>'];

    expect(PlaceholderRenderer::plain('Halo {{Nama}}.', $values))->toBe('Halo {{Rahasia}} <script>alert(1)</script>.')
        ->and(PlaceholderRenderer::segments('{{Nama}}', $values)[0]['value'])->toBe('{{Rahasia}} <script>alert(1)</script>');
});

it('replaces in one pass, so a value cannot pull in another field', function () {
    expect(PlaceholderRenderer::plain('{{A}} dan {{B}}', ['A' => '{{B}}', 'B' => 'x']))->toBe('{{B}} dan x');
});

it('leaves an invalid group literal and shows a missing value in brackets', function () {
    expect(PlaceholderRenderer::plain('{{ Nama }} {{Nama}} {{Hilang}}', ['Nama' => 'Budi']))->toBe('{{ Nama }} Budi [Hilang]');
});
