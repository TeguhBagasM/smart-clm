<?php

use App\Support\Comparison\ArticleSplitter;

it('splits on Pasal lines and keeps the preamble', function () {
    $result = (new ArticleSplitter)->split(<<<'TXT'
PERJANJIAN KERJA SAMA
Yang bertanda tangan di bawah ini:

BAB I
KETENTUAN UMUM

Pasal 1
Dalam perjanjian ini yang dimaksud dengan
Pihak Pertama adalah PT Maju.
Halaman 2 dari 9

Pasal 2A - Jangka Waktu
Perjanjian berlaku selama 2 (dua) tahun.
TXT);

    expect($result['strategy'])->toBe('pasal')
        ->and(array_column($result['articles'], 'number_label'))->toBe([null, 'BAB I', 'Pasal 1', 'Pasal 2a'])
        ->and($result['articles'][0]['heading'])->toBe('Pembukaan')
        ->and($result['articles'][1]['body'])->toBe('KETENTUAN UMUM')
        ->and($result['articles'][2]['body'])->toBe('Dalam perjanjian ini yang dimaksud dengan Pihak Pertama adalah PT Maju.')
        ->and($result['articles'][3]['heading'])->toBe('Jangka Waktu');
});

it('does not cut at a reference to a Pasal inside a sentence', function () {
    $result = (new ArticleSplitter)->split("Pasal 1\nSesuai Pasal 5 ayat (1) pihak wajib hadir.\nPasal 2\nIsi dua.");

    expect($result['articles'])->toHaveCount(2)->and($result['articles'][0]['body'])->toContain('Pasal 5 ayat (1)');
});

it('keeps list items on their own lines', function () {
    $result = (new ArticleSplitter)->split("Pasal 1\nPara pihak sepakat:\na. membayar tepat waktu;\nb. menjaga kerahasiaan.\nPasal 2\nIsi.");

    expect($result['articles'][0]['body'])->toBe("Para pihak sepakat:\na. membayar tepat waktu;\nb. menjaga kerahasiaan.");
});

it('falls back to numbered headings for documents written in the system', function () {
    $result = (new ArticleSplitter)->split("1. TUJUAN\nMenjelaskan prosedur.\n\n2. RUANG LINGKUP\nBerlaku untuk semua unit.");

    expect($result['strategy'])->toBe('numbered')->and(array_column($result['articles'], 'number_label'))->toBe(['1.', '2.']);
});

it('falls back to chunks when no structure is found', function () {
    $result = (new ArticleSplitter)->split(str_repeat('Kalimat biasa tanpa struktur pasal apapun di sini. ', 80));

    expect($result['strategy'])->toBe('chunks')->and($result['articles'])->not->toBeEmpty()->and($result['articles'][0]['number_label'])->toBeNull();
});
