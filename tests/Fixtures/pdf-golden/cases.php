<?php

/**
 * Payload catalogue for the PDF golden files.
 *
 * Every payload is what App\Actions\Dcs\RenderVersion::engineInput() sends to a
 * PdfEngine, built by hand and deterministic (no randomness, no clock) so the
 * same case always asks the Node engine the same question. Binary parts are
 * references ("@asset:name") resolved by generate.php.
 *
 * @return array<string, array<string, mixed>>
 */

use App\Support\Dcs\Header\HeaderLayout;

$base = function (array $over = []): array {
    $headerValues = $over['headerValues'] ?? [];
    unset($over['headerValues']);

    $payload = array_merge([
        'documentNumber' => 'SOP-HED-27-00-26',
        'title' => 'Prosedur Pengadaan Barang dan Jasa',
        'docTypeCode' => 'SOP',
        'department' => 'HED',
        'major' => 1,
        'minor' => 0,
        'purpose' => 'Menetapkan langkah pengadaan barang dan jasa agar terkendali.',
        'scope' => 'Berlaku untuk seluruh unit kerja yang mengajukan pengadaan.',
        'sections' => [],
        'flow' => ['mode' => 'none'],
        'letterhead' => ['companyName' => 'PT Contoh Nusantara', 'companyAddress' => 'Jl. Merdeka No. 10, Bandung 40111'],
        'signatureGroups' => [['heading' => 'Persetujuan', 'columns' => [
            ['label' => 'Dibuat oleh', 'role' => 'staff', 'roleName' => 'Staf Pengadaan'],
            ['label' => 'Disetujui oleh', 'role' => 'manajemen', 'roleName' => 'Manajer HED'],
        ]]],
        'revisionHistory' => [],
        'appendix' => [],
        'margins' => ['top' => 56, 'right' => 56, 'bottom' => 56, 'left' => 56],
        'headerStyle' => 'iso',
        'bodyStyle' => 'standard',
        'docTypeName' => 'Standard Operating Procedure',
        'documentLevel' => 'Internal',
        'processOwner' => 'HED Departement',
        'language' => 'Indonesia',
        'issuedDateIso' => null,
        'header' => ['layout' => HeaderLayout::default(), 'values' => [
            'documentNumber' => 'SOP-HED-27-00-26',
            'title' => 'Prosedur Pengadaan Barang dan Jasa',
            'docTypeName' => 'Standard Operating Procedure',
            'docTypeCode' => 'SOP',
            'issueNumber' => '1',
            'revisionNumber' => '00',
            'revisionLabel' => '00/26',
            'language' => 'Indonesia',
            'documentLevel' => 'Internal',
            'processOwner' => 'HED Departement',
            'department' => 'HED',
            'owner' => 'Dewi Ayu Kusumawardhani',
        ]],
        'statusLabel' => 'Draft',
        'confidentialityNotice' => null,
    ], $over);

    if ($payload['header'] !== null) {
        $payload['header']['values'] = array_merge($payload['header']['values'], $headerValues);
    }

    return $payload;
};

$paragraphs = function (int $count, string $topic): string {
    $lines = [];
    for ($i = 1; $i <= $count; $i++) {
        $lines[] = "Paragraf $i tentang $topic. Setiap unit kerja wajib memastikan bahwa **dokumen pendukung** lengkap sebelum diajukan, "
            .'dan pemeriksaan dilakukan oleh pihak yang berwenang sesuai tingkat persetujuan yang berlaku di perusahaan.';
        if ($i % 4 === 0) {
            $lines[] = "  - Butir pertama untuk paragraf $i, diperiksa oleh atasan langsung";
            $lines[] = "  - Butir kedua untuk paragraf $i dengan **penekanan** pada batas waktu";
            $lines[] = '    - Sub-butir bersarang untuk memastikan indentasi daftar terbaca benar';
        }
        if ($i % 7 === 0) {
            $lines[] = '';
        }
    }

    return implode("\n", $lines);
};

$table = function (int $rows): array {
    $out = [];
    for ($i = 1; $i <= $rows; $i++) {
        $out[] = [(string) $i, "Uraian pekerjaan nomor $i yang cukup panjang sehingga harus dipotong ke beberapa baris di dalam sel tabel", 'Staf Unit '.(($i % 5) + 1), $i % 3 === 0 ? 'Wajib dilampiri bukti pendukung berupa berita acara serah terima barang dan jasa' : 'Rutin'];
    }

    return ['columns' => ['No', 'Uraian', 'Penanggung Jawab', 'Keterangan'], 'rows' => $out];
};

$flowSteps = [
    ['id' => 's1', 'order' => 1, 'type' => 'start', 'text' => 'Mulai', 'actor' => 'Pemohon', 'nextId' => 's2'],
    ['id' => 's2', 'order' => 2, 'type' => 'process', 'text' => 'Mengajukan permohonan pengadaan', 'actor' => 'Pemohon', 'nextId' => 's3', 'pic' => 'Staf Unit'],
    ['id' => 's3', 'order' => 3, 'type' => 'decision', 'text' => 'Disetujui atasan?', 'actor' => 'Atasan', 'yesId' => 's4', 'noId' => 's2'],
    ['id' => 's4', 'order' => 4, 'type' => 'process', 'text' => 'Memproses pembayaran kepada pemasok', 'actor' => 'Finance', 'nextId' => 's5', 'pic' => 'Staf Finance'],
    ['id' => 's5', 'order' => 5, 'type' => 'document', 'text' => 'Bukti pembayaran', 'actor' => 'Finance', 'nextId' => 's6'],
    ['id' => 's6', 'order' => 6, 'type' => 'end', 'text' => 'Selesai', 'actor' => 'Pemohon'],
];

$signers = fn (int $groups, int $perGroup): array => array_map(fn (int $g): array => [
    'heading' => 'Kelompok '.($g + 1),
    'columns' => array_map(fn (int $c): array => [
        'label' => ['Dibuat oleh', 'Diperiksa oleh', 'Disetujui oleh', 'Diketahui oleh'][$c % 4],
        'role' => ['staff', 'supervisor', 'manajemen'][$c % 3],
        'roleName' => 'Jabatan '.($g * $perGroup + $c + 1),
    ], range(0, $perGroup - 1)),
], range(0, $groups - 1));

$revisions = [
    ['major' => 1, 'minor' => 0, 'changeSummary' => 'Penerbitan pertama', 'effectiveAt' => '2025-03-01T00:00:00.000Z', 'createdAt' => '2025-02-20T00:00:00.000Z'],
    ['major' => 1, 'minor' => 1, 'changeSummary' => 'Penambahan batas nilai pengadaan langsung', 'effectiveAt' => '2025-09-15T00:00:00.000Z', 'createdAt' => '2025-09-01T00:00:00.000Z'],
];

return [
    'short-iso' => $base(['sections' => [
        ['heading' => 'Prosedur', 'content' => "Pemohon mengisi formulir pengadaan.\nAtasan memeriksa dan menyetujui.\nFinance memproses pembayaran."],
    ]]),

    'multipage-standard' => $base([
        'title' => 'Pedoman Operasional Lengkap',
        'sections' => [
            ['heading' => 'Ketentuan Umum', 'content' => $paragraphs(40, 'ketentuan umum')],
            ['heading' => 'Tanggung Jawab', 'content' => $paragraphs(30, 'pembagian tanggung jawab')],
            ['heading' => 'Daftar Pekerjaan', 'content' => 'Tabel berikut merinci pekerjaan.', 'table' => $table(45)],
            ['heading' => 'Penutup', 'content' => $paragraphs(10, 'penutup')],
        ],
        'revisionHistory' => $revisions,
        'appendix' => [['label' => 'Formulir Pengadaan', 'url' => 'https://contoh.example/formulir-pengadaan.pdf'], ['label' => 'Daftar Pemasok Terpilih', 'url' => 'https://contoh.example/pemasok']],
        'confidentialityNotice' => 'Dokumen ini milik PT Contoh Nusantara dan bersifat rahasia. Dilarang menggandakan tanpa izin tertulis.',
    ]),

    'multipage-formal' => $base([
        'title' => 'Standar Prosedur Formal',
        'bodyStyle' => 'formal',
        'sections' => [
            ['heading' => 'Definisi', 'content' => $paragraphs(35, 'definisi istilah')],
            ['heading' => 'Prosedur Pelaksanaan', 'content' => $paragraphs(35, 'pelaksanaan prosedur')],
            ['heading' => 'Rekaman', 'content' => 'Daftar rekaman yang wajib disimpan.', 'table' => $table(30)],
        ],
        'revisionHistory' => $revisions,
        'signatureGroups' => $signers(2, 2),
    ]),

    'flow-swimlane' => $base([
        'title' => 'Alur Pengadaan Swimlane',
        'sections' => [['heading' => 'Uraian', 'content' => 'Lihat diagram alir pada halaman berikut.']],
        'flow' => ['mode' => 'builder', 'steps' => $flowSteps, 'lanes' => ['Pemohon', 'Atasan', 'Finance'], 'displayStyle' => 'diagram'],
    ]),

    'flow-swimlane-formal' => $base([
        'title' => 'Alur Pengadaan Formal',
        'bodyStyle' => 'formal',
        'sections' => [['heading' => 'Uraian', 'content' => 'Lihat diagram alir pada halaman berikut.']],
        'flow' => ['mode' => 'builder', 'steps' => $flowSteps, 'lanes' => ['Pemohon', 'Atasan', 'Finance'], 'displayStyle' => 'diagram'],
    ]),

    'flow-table' => $base([
        'title' => 'Alur Pengadaan Tabel',
        'sections' => [['heading' => 'Uraian', 'content' => 'Alur disajikan sebagai tabel.']],
        'flow' => ['mode' => 'builder', 'steps' => $flowSteps, 'lanes' => ['Pemohon', 'Atasan', 'Finance'], 'displayStyle' => 'table'],
    ]),

    'flow-manual-grid' => $base([
        'title' => 'Alur dengan Posisi dan Jalur Manual',
        'sections' => [['heading' => 'Uraian', 'content' => 'Diagram dengan baris tetap, ukuran kotak, dan sisi keluar masuk yang dipilih penyusun.']],
        'flow' => ['mode' => 'builder', 'lanes' => ['Pemohon', 'Atasan', 'Finance'], 'displayStyle' => 'diagram', 'steps' => [
            ['id' => 'a', 'order' => 1, 'type' => 'start', 'text' => 'Mulai', 'actor' => 'Pemohon', 'row' => 0, 'nextId' => 'b'],
            ['id' => 'b', 'order' => 2, 'type' => 'process', 'text' => 'Menyusun permohonan dengan lampiran lengkap dan terstruktur', 'actor' => 'Pemohon', 'row' => 1, 'size' => ['w' => 1.2, 'h' => 1.6], 'nextId' => 'c', 'ports' => ['next' => ['exit' => 'right', 'entry' => 'left']]],
            ['id' => 'c', 'order' => 3, 'type' => 'decision', 'text' => 'Lengkap?', 'actor' => 'Atasan', 'row' => 1, 'yesId' => 'd', 'noId' => 'b', 'ports' => ['no' => ['exit' => 'top', 'entry' => 'top']]],
            ['id' => 'd', 'order' => 4, 'type' => 'data', 'text' => 'Data pemasok', 'actor' => 'Finance', 'row' => 2, 'nextId' => 'e'],
            ['id' => 'e', 'order' => 5, 'type' => 'connector', 'text' => 'A', 'actor' => 'Finance', 'row' => 3, 'nextId' => 'f'],
            ['id' => 'f', 'order' => 6, 'type' => 'end', 'text' => 'Selesai', 'actor' => 'Pemohon', 'row' => 4],
        ]],
    ]),

    'flow-image' => $base([
        'title' => 'Alur dari Gambar',
        'sections' => [['heading' => 'Uraian', 'content' => 'Diagram diunggah sebagai gambar.']],
        'flow' => ['mode' => 'image', 'image' => '@asset:flow.png', 'mimeType' => 'image/png'],
    ]),

    'many-signers' => $base([
        'title' => 'Dokumen dengan Banyak Penandatangan',
        'sections' => [['heading' => 'Isi', 'content' => $paragraphs(6, 'penandatanganan')]],
        'signatureGroups' => $signers(3, 3),
    ]),

    'many-signers-formal' => $base([
        'title' => 'Banyak Penandatangan Gaya Formal',
        'bodyStyle' => 'formal',
        'sections' => [['heading' => 'Isi', 'content' => $paragraphs(6, 'penandatanganan formal')]],
        'signatureGroups' => $signers(2, 3),
        'revisionHistory' => $revisions,
    ]),

    'unicode-latin' => $base([
        'title' => 'Kebijakan “Jarak Jauh” – Edisi Ke-2 …',
        'sections' => [
            ['heading' => 'Karakter Latin dan Tanda Baca', 'content' => "Nama: José Muñoz-Pérez, François Müller, Zoë Kožušník, Øystein Åberg.\nTanda baca: “kutip ganda”, ‘kutip tunggal’, — pagar panjang —, – pagar pendek –, … elipsis, © ® ™ ° ± × ÷ € £ ¥.\nIndonesia: Dewi Ayu Kusumawardhani menyerahkan laporan keuangan senilai Rp 1.250.000,00."],
        ],
        'headerValues' => ['title' => 'Kebijakan “Jarak Jauh” – Edisi Ke-2 …', 'owner' => 'José Muñoz-Pérez'],
    ]),

    'unicode-outside-winansi' => $base([
        'title' => 'Dokumen dengan Karakter di Luar WinAnsi',
        'sections' => [['heading' => 'Simbol', 'content' => 'Alur → berikutnya ✓ selesai, rumus ≥ 5, huruf 日本語 dan emoji 😀.']],
    ]),

    'very-long-text' => $base([
        'title' => 'Dokumen Sangat Panjang',
        'sections' => [
            ['heading' => 'Bagian Sangat Panjang', 'content' => $paragraphs(420, 'teks yang sangat panjang')],
            ['heading' => 'Tanpa Spasi', 'content' => 'https://contoh.example/'.str_repeat('segmen-sangat-panjang-tanpa-spasi-', 14).' dan kata '.str_repeat('Pneumonoultramicroscopicsilicovolcanoconiosis', 3)],
            ['heading' => 'Tabel Panjang', 'content' => '', 'table' => $table(120)],
        ],
        'revisionHistory' => $revisions,
    ]),

    'simple-header-letterhead' => $base([
        'title' => 'Memo dengan Kop Sederhana',
        'headerStyle' => 'simple',
        'header' => null,
        'letterhead' => ['companyName' => 'PT Contoh Nusantara', 'companyAddress' => 'Jl. Merdeka No. 10, Bandung 40111', 'logo' => '@asset:logo.png', 'logoMimeType' => 'image/png'],
        'sections' => [['heading' => 'Isi Memo', 'content' => 'Diberitahukan bahwa rapat koordinasi akan diadakan pada hari Senin.']],
        'signatureGroups' => [],
    ]),

    'iso-header-logo-formal' => $base([
        'title' => 'Kop dengan Logo dan Gaya Formal',
        'bodyStyle' => 'formal',
        'letterhead' => ['companyName' => 'PT Contoh Nusantara', 'companyAddress' => 'Jl. Merdeka No. 10, Bandung 40111', 'logo' => '@asset:logo.png', 'logoMimeType' => 'image/png'],
        'sections' => [['heading' => 'Ketentuan', 'content' => $paragraphs(12, 'kop dengan logo')]],
        'confidentialityNotice' => 'Dokumen ini bersifat rahasia.',
    ]),

    'no-signature-no-header' => $base([
        'title' => 'Tanpa Lembar Pengesahan',
        'signatureGroups' => [],
        'sections' => [['heading' => 'Isi', 'content' => 'Dokumen tanpa lembar pengesahan.']],
    ]),

    'iso-fixed-box' => $base([
        'title' => 'Dokumen dengan Kotak ISO Tetap yang Judulnya Sengaja Dibuat Sangat Panjang Sekali Supaya Terpotong',
        'header' => null,
        'letterhead' => ['companyName' => 'PT Contoh Nusantara', 'companyAddress' => 'Jl. Merdeka No. 10', 'logo' => '@asset:logo.jpg', 'logoMimeType' => 'image/jpeg'],
        'sections' => [['heading' => 'Isi', 'content' => $paragraphs(5, 'kotak iso tetap')]],
        'language' => 'Indonesia / English',
        'processOwner' => '',
    ]),

    'memo-jpeg-logo-long-address' => $base([
        'title' => 'Memo dengan Logo JPEG dan Alamat Panjang yang Harus Dipotong Tiga Baris',
        'headerStyle' => 'simple',
        'header' => null,
        'issuedDateIso' => '2026-03-05T00:00:00.000Z',
        'letterhead' => ['companyName' => 'PT Contoh Nusantara Sejahtera Makmur Abadi', 'companyAddress' => 'Jl. Jenderal Sudirman Kav. 52-53, Gedung Menara Contoh Lantai 21, Senayan, Kebayoran Baru, Jakarta Selatan 12190, Indonesia, Telepon (021) 5551234, Faksimile (021) 5551235', 'logo' => '@asset:logo.jpg', 'logoMimeType' => 'image/jpeg'],
        'sections' => [['heading' => 'Isi Memo', 'content' => 'Rapat koordinasi akan diadakan.']],
        'signatureGroups' => [],
        'revisionHistory' => [['major' => 1, 'minor' => 0, 'changeSummary' => null, 'effectiveAt' => null, 'createdAt' => '2025-02-20T00:00:00.000Z']],
    ]),

    'iso-header-alpha-logo-margins' => $base([
        'title' => 'Margin Khusus dan Logo Transparan',
        'margins' => ['top' => 40, 'right' => 90, 'bottom' => 70, 'left' => 30],
        'letterhead' => ['companyName' => 'PT Contoh', 'companyAddress' => 'Jl. A', 'logo' => '@asset:logo-alpha.png', 'logoMimeType' => 'image/png'],
        'sections' => [['heading' => 'Isi', 'content' => $paragraphs(14, 'margin khusus'), 'table' => $table(12)]],
    ]),

    'flow-legacy-single-column' => $base([
        'title' => 'Alur Lama Kolom Tunggal',
        'sections' => [['heading' => 'Uraian', 'content' => 'Alur berurutan tanpa garis graf.']],
        'flow' => ['mode' => 'builder', 'steps' => [
            ['order' => 1, 'type' => 'start', 'text' => 'Mulai'],
            ['order' => 2, 'type' => 'process', 'text' => 'Menerima permohonan dari pemohon'],
            ['order' => 3, 'type' => 'decision', 'text' => 'Dokumen lengkap?', 'noTargetOrder' => 2],
            ['order' => 4, 'type' => 'document', 'text' => 'Menerbitkan surat'],
            ['order' => 5, 'type' => 'end', 'text' => 'Selesai'],
        ]],
    ]),

    'flow-legacy-swimlane' => $base([
        'title' => 'Alur Lama Swimlane',
        'bodyStyle' => 'formal',
        'sections' => [['heading' => 'Uraian', 'content' => 'Alur berurutan dengan lajur.']],
        'flow' => ['mode' => 'builder', 'lanes' => ['Pemohon', 'Atasan Langsung dari Pemohon yang Sangat Panjang', 'Finance'], 'steps' => [
            ['order' => 1, 'type' => 'start', 'text' => 'Mulai', 'actor' => 'Pemohon'],
            ['order' => 2, 'type' => 'process', 'text' => 'Mengajukan', 'actor' => 'Pemohon'],
            ['order' => 3, 'type' => 'decision', 'text' => 'Setuju?', 'actor' => 'Atasan Langsung dari Pemohon yang Sangat Panjang', 'noTargetOrder' => 2],
            ['order' => 4, 'type' => 'process', 'text' => 'Membayar', 'actor' => 'Finance'],
            ['order' => 5, 'type' => 'end', 'text' => 'Selesai', 'actor' => 'Finance'],
        ]],
    ]),

    'flow-colors-predefined-overflow' => $base([
        'title' => 'Simbol Warna dan Teks Meluber',
        'sections' => [['heading' => 'Uraian', 'content' => 'Simbol berwarna, proses terdefinisi, dan teks yang melebihi kotak.']],
        'flow' => ['mode' => 'builder', 'displayStyle' => 'diagram', 'steps' => [
            ['id' => 'a', 'order' => 1, 'type' => 'start', 'text' => 'Mulai', 'color' => '#0f9d58', 'textColor' => '#ffffff', 'nextId' => 'b'],
            ['id' => 'b', 'order' => 2, 'type' => 'predefined', 'text' => 'Menjalankan SOP-HED-01 tentang penerimaan barang dengan semua langkah pemeriksaan yang ada di dalamnya dan seterusnya sampai panjang sekali melebihi kotak', 'color' => '#abc', 'nextId' => 'c'],
            ['id' => 'c', 'order' => 3, 'type' => 'data', 'text' => 'Data pesanan', 'nextId' => 'd'],
            ['id' => 'd', 'order' => 4, 'type' => 'decision', 'text' => 'Sesuai spesifikasi yang diminta oleh pemohon?', 'yesId' => 'e', 'noId' => 'b'],
            ['id' => 'e', 'order' => 5, 'type' => 'end', 'text' => 'Selesai', 'color' => 'bukan-warna'],
        ]],
    ]),

    'signature-columns-legacy' => $base([
        'title' => 'Kolom Tanda Tangan Lama',
        'signatureGroups' => [],
        'signatureColumns' => [['label' => 'Dibuat oleh:', 'role' => 'staff'], ['label' => 'Disetujui oleh', 'role' => 'manajemen', 'roleName' => 'Direktur Utama']],
        'sections' => [['heading' => 'Isi', 'content' => 'Kolom tanda tangan datar.']],
        'revisionHistory' => [['major' => 1, 'minor' => 0, 'changeSummary' => 'Penerbitan pertama yang catatannya cukup panjang sehingga harus dipotong di tabel', 'effectiveAt' => '2026-01-05T00:00:00.000Z', 'createdAt' => '2026-01-01T00:00:00.000Z']],
    ]),

    'revision-five-rows-no-fit' => $base([
        'title' => 'Riwayat Revisi Banyak',
        'bodyStyle' => 'formal',
        'signatureGroups' => $signers(3, 3),
        'revisionHistory' => array_map(fn (int $i): array => ['major' => 1, 'minor' => $i, 'changeSummary' => "Perubahan ke-$i", 'effectiveAt' => '2025-0'.min($i + 1, 9).'-01T00:00:00.000Z', 'createdAt' => '2025-01-01T00:00:00.000Z'], range(0, 5)),
        'sections' => [['heading' => 'Isi', 'content' => 'Tabel revisi tidak muat di bawah lembar pengesahan.']],
    ]),
];
