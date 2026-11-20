# 07 — Fase C5: impor perpustakaan klausul dan template dari Node

Tanggal: 2026-11-20. Aturan sama seperti impor master data (B3): `--dry-run`, per perusahaan, semua-atau-tidak, dicocokkan lewat kunci Node (`legacy_key`), idempoten, `--rollback`, fixture sintetis, dan impor sungguhan ditolak bila dry run tidak bersih. Tanpa controller, route, atau UI (C6). Tidak ada perubahan skema.

## 1. Dikerjakan

**Perintah:** `php artisan contract-templates:import-legacy --tenant=t-01 [--dry-run] [--rollback]`.

**Urutan impor** (satu transaksi; `App\Actions\Templates\ImportLegacyTemplateLibrary`):
1. **Kolom isian** (`LegacyVariableImporter`): tabel Node `variables`, dicocokkan lewat `key`. Baris tanpa `tenantId` dianggap milik `t-01` (db.ts:346). Kode harus sesuai aturan placeholder. Kolom bawaan dengan kode yang sama **diadopsi** (hanya menerima kunci Node); kolom buatan tangan dengan kode yang sama ditolak. Sumber otomatis diambil dari katalog bawaan (`source_field`) bila kodenya cocok.
2. **Pasal** (`LegacyClauseImporter`): tabel Node `clauses`. Kategori dicocokkan dengan nama kategori perusahaan (dari impor master data). Isi HTML diubah menjadi teks biasa (`NodeText`): paragraf dan baris baru dipertahankan, format (tebal, warna, tabel, gambar) dibuang dan dilaporkan sebagai peringatan. Placeholder yang tidak dikenal dicatat sebagai **tidak dikenal** (membuat dry run tidak bersih). Nomor revisi = `version` Node.
3. **Templat** (`LegacyTemplateImporter`): tabel Node `templates`. Templat yang dipakai di Node menjadi **versi terbit 1** dengan salinan pasal, pihak, dan kolom isian (wajib). Pasal yang tidak ada, kolom yang tidak ada, kategori yang tidak ada, atau pihak kosong membuat baris dilewati dan dilaporkan.

**Pengecualian:** pasal dan templat dari perusahaan lain tidak terbaca (pembaca menyaring `data->tenantId`). Templat bawaan yang masih draf dan punya kunci Node yang sama dilaporkan untuk diterbitkan manual, tidak diterbitkan otomatis.

**Rollback** (`RollbackLegacyTemplateLibrary`): menghapus pasal dan kolom isian yang dibuat impor, dan draf templat yang dibuat impor. Bawaan sistem hanya kehilangan kuncinya. **Templat yang sudah terbit tidak bisa dihapus** (versi terbit beku, C1), jadi rollback ditolak dengan daftar penyebabnya; yang bisa dilakukan adalah menyembunyikan templat. Pasal yang masih disalin templat dan kolom isian yang masih dipakai juga menolak rollback.

**Audit:** `contract_templates.import.imported` dan `contract_templates.import.rolled_back`, tanpa isi teks (hanya jumlah per daftar).

## 2. Bukti

- **Tes baru (14)**:
  - `tests/Feature/Actions/Templates/ImportLegacyTemplateLibraryTest.php` (11): dry run melaporkan `{{Tidak}}` dan tidak menulis apa pun; impor bersih menghasilkan pasal tanpa HTML dengan nomor revisi, templat terbit versi 1 dengan salinan pasal (`source_clause_id`, urutan, pihak, kolom wajib), peringatan format, dan **tidak membawa** milik PT lain; impor ulang tidak membuat apa pun dua kali; adopsi bawaan (4 kolom diadopsi, 3 pasal tidak berubah, jumlah templat tetap, draf bawaan dilaporkan); impor tidak bersih tidak menulis apa pun meski real run; kategori tidak ada melewati pasal dan templat yang memakainya dengan alasan; judul yang dipakai pasal buatan tangan ditolak; perusahaan tanpa `legacy_id` ditolak; rollback ditolak bila ada templat terbit; rollback berhasil bila tidak ada templat, dan audit tercatat.
  - `tests/Feature/Actions/Templates/ImportLegacyTemplateLibraryCommandTest.php` (4): tanpa `--tenant` ditolak; `t-77` tanpa padanan ditolak; dry run tidak bersih menolak impor sungguhan dan tidak menulis apa pun; impor bersih dengan dry run terlebih dahulu.
- **Fixture:** `tests/Support/LegacyTemplateFixture.php`, SQLite dengan bentuk tabel Node (`clauses`, `templates`, `variables`, `tenants`). Semua nilai fiktif; ada pasal dan kolom milik PT lain.
- **Perbaikan tes sendiri:** fixture `update()` awalnya memakai `+` pada array, yang membuang perubahan; diganti `array_merge`. Tes rollback awalnya mengharapkan 1 penolakan, padahal 7 (benar: templat terbit, 3 pasal disalin, 3 kolom dipakai). Dipisah menjadi dua tes.
- **Suite penuh** (`php -d memory_limit=512M`): **PostgreSQL 1718/1718**; **SQLite 1704 lulus, 14 dilewati** (tes khusus PostgreSQL). 0 gagal.
- Pint lulus. PHPStan bersih untuk `app/Actions/Templates`, `app/Support/Templates`, dan perintah impor.
- Ukuran: terbesar `LegacyTemplateImporter.php` 191 baris; tidak ada yang > 200.

## 3. Beda dari Node dan dari dokumen

- **Versi Node:** Node menyimpan redaksi templat di tempat dan hanya nomor versinya. Impor membawa isi terakhir sebagai versi 1; nomor versi Node yang lebih tinggi dilaporkan.
- **Format:** Node menyimpan HTML kaya; di sini teks biasa. Format yang hilang dilaporkan per pasal.
- **Templat dipublikasikan otomatis** (Node: sudah dipakai). Bawaan draf tetap draf.
- **Rollback:** Node tidak punya konsep ini. Batas terbit beku membuat rollback templat tidak mungkin; ini beda dari impor master data.
- **Placeholder yang tidak dikenal menggagalkan impor.** Node mengizinkan kunci apa pun; di sini harus ada kolom isiannya lebih dulu.

## 4. Konflik dokumen vs kode

Tidak ada baru. Node `db.ts:346` (variabel tanpa `tenantId` milik `t-01`) sudah dikonfirmasi dan dipakai.

## 5. Risiko dan yang belum diverifikasi

- **Belum diuji dengan data Node sungguhan.** Hanya fixture sintetis. Bentuk JSON produksi diasumsikan sama dengan `src/types.ts` dan `db.ts`. Sebelum impor sungguhan, jalankan `--dry-run` terhadap salinan database Node dan periksa daftar galat dan nilai tidak dikenal.
- **Ketergantungan urutan:** kategori harus diimpor lebih dulu lewat `master-data:import-legacy`. Tanpa itu, semua pasal dengan kategori itu gagal.
- **Rollback templat terbit tidak tersedia.** Jika salah impor, templatnya hanya bisa disembunyikan. Ini risiko operasional; lihat pertanyaan 1.
- **Isi pasal diubah ke teks biasa.** Format tebal dan tabel hilang; peringatan menyebut setiap pasal yang terkena. Pengguna perlu meninjau.
- **Perbandingan isi pasal bawaan** tidak dilakukan: pasal yang sudah ada dengan kunci yang sama dibiarkan apa adanya, walau teks Node berbeda.
- **Pemeriksaan browser** belum dilakukan (belum ada halaman).
- DB dev belum disentuh (dijadwalkan C6).

## 6. Temuan di luar lingkup

- Impor master data (`ImportLegacyMasterData`) tidak melaporkan templat atau pasal; itu tepat, karena tanggung jawab C5.

## 7. Pertanyaan terbuka

1. **Rollback templat yang sudah terbit:** rekomendasi: tetap ditolak, dan kalau perlu ditarik, ada tindakan "tarik dari pilihan" (sembunyikan) yang sudah tersedia. Alternatif: mengimpor templat sebagai draf agar bisa dihapus, lalu diterbitkan manual. Keputusan ada pada Anda. [Keyakinan sedang]
2. **Dry run terhadap salinan database Node:** setuju dijalankan sebelum impor sungguhan, dan Anda sendiri yang memeriksa daftar galat dan nilai tidak dikenal? [Keyakinan tinggi]
3. **Lanjut ke C6** (controller, route, halaman, tes HTTP; migrasi dan isi DB dev; berhenti wajib setelah C6)? Rekomendasi: ya. [Keyakinan tinggi]

## Berkas

Dibuat: `app/Support/Templates/Legacy/{LegacyLibraryReader,NodeText,LegacyVariableImporter,LegacyClauseImporter,LegacyTemplateImporter}.php`, `app/Actions/Templates/{ImportLegacyTemplateLibrary,RollbackLegacyTemplateLibrary}.php`, `app/Console/Commands/ImportLegacyTemplateLibraryCommand.php`, `tests/Support/LegacyTemplateFixture.php`, `tests/Feature/Actions/Templates/{ImportLegacyTemplateLibraryTest,ImportLegacyTemplateLibraryCommandTest}.php`, `docs-tracked/vendor-template/07-c5-impor-node.md`.

Diubah: `config/contract_templates.php` (blok `import`), `docs-tracked/vendor-template/PROGRESS.md`.
