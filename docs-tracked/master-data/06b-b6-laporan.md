# 06b — Laporan Blok B6: importer dari Node

Dibuat 2026-11-20. Importer dibangun dan diuji **hanya** dengan fixture sintetis (SQLite, bentuk tabel Node: `tenants`, `categories`, `settings`, `employees`, `contracts`, JSON sebagai teks). Tidak pernah dijalankan terhadap database selain DB tes, dan tidak meminta hasil `queries.sql`.

## 1. Dikerjakan

`php artisan master-data:import-legacy --tenant=t-01 [--dry-run] [--rollback] [--force-unclean]`

- **Per tenant**: `--tenant` wajib (tidak ada impor massal); tenant Node dipetakan ke PT Laravel lewat `tenants.legacy_id`. Tanpa padanan: dilewati dengan pesan dan exit gagal.
- **Semua atau tidak sama sekali**: satu transaksi per PT; galat apa pun membatalkan seluruh PT itu.
- **Aman diulang**: baris dicocokkan dengan kunci Node (`legacy_key`; platform/kategori/hubungan kerja/tipe pihak = nilainya, mata uang = kode, jenis dokumen dan kelompok mitra = `id` Node, mitra = `kelompok|nama`, karyawan = `id`). Run kedua tidak mengubah apa pun dan tidak menimpa perubahan manusia.
- **Mengadopsi nilai bawaan**: baris dengan nama atau kode sama yang belum bertanda Node (hasil `seed-defaults`) hanya menerima kuncinya, bukan digandakan (meniru Node `server.ts:7765`).
- **`--dry-run`** menjalankan seluruh impor lalu membatalkannya, dan melaporkan: jumlah per daftar (dibuat/diadopsi/tidak berubah/dilewati), **nilai yang tidak ada di daftar acuan beserta jumlah baris** (kategori, jenis dokumen, platform, mitra, hubungan kerja, departemen/divisi, tipe pihak, mata uang yang dipakai kontrak Node; kategori pada jenis dokumen; platform pada kelompok mitra; departemen karyawan; departemen Node yang belum ada di DCS), **baris yang gagal dipetakan** (hanya id Node dan alasan), dan peringatan.
- **Tanpa dry-run yang bersih, impor menolak** (exit gagal, tidak menulis apa pun) kecuali `--force-unclean`. "Bersih" = tidak ada baris gagal dipetakan dan tidak ada nilai yang hilang dari daftar. Pengecekan ini otomatis: perintah selalu menjalankan dry-run dulu.
- **`--rollback`** (dengan `--dry-run` untuk pratinjau): menghapus baris bertanda Node milik PT itu; nilai bawaan yang hanya diadopsi hanya dilepas kuncinya. Bila data yang dibuat sesudah impor masih memakai baris hasil impor (mitra buatan sendiri dalam kelompok impor, karyawan pada hubungan kerja impor), tidak ada yang diubah dan laporan menjelaskan.
- **Audit**: `master_data.import.imported` dan `master_data.import.rolled_back` (tanpa pelaku, hanya angka).
- Tidak diimpor, sengaja: gaji dan NIK (kolomnya tidak pernah dibaca: pembaca hanya memilih nama, jabatan, departemen, tanggal mulai); rekening, NPWP, dan kontak mitra (`operatorDetails`); daftar PT lain pada kelompok mitra; template dan format nomor jenis dokumen; baris contoh Node (`EMP001`–`EMP003`, kontrak `HR-PKWT-2026-0002`, di `config/master_data.php` → `import`); kontrak dan vendor.
- Entri "Lainnya" di Node diimpor tersembunyi dan dilaporkan (P11). Kelompok mitra yang hanya menyebut platform yang tidak ada dilewati (kalau tidak, ia berubah menjadi berlaku untuk semua platform).

## 2. Bukti

- 17 tes baru: `ImportLegacyMasterDataTest` (9) dan `ImportLegacyMasterDataCommandTest` (8). Mencakup dry-run tidak menulis, jumlah dan nilai tak dikenal tepat, demo dan tenant lain tidak ikut, penanda karyawan dan relasi, gaji/NIK/rekening/nama orang tidak ada di tabel, laporan, audit, maupun keluaran perintah, idempoten, adopsi nilai bawaan, atomik (galat di tengah jalan), perintah menolak tanpa `--tenant` dan tanpa dry-run bersih, flag eksplisit, rollback (pratinjau, diblokir bila ada ketergantungan, nilai bawaan dilepas, baris buatan sendiri tetap).
- Suite penuh: PostgreSQL 1448/1448; SQLite 1435 lulus, 13 dilewati (`memory_limit=512M`). PHPStan: tidak ada temuan di berkas importer. Ukuran berkas terbesar `ImportLegacyMasterDataTest.php` 154 baris, `ImportLegacyMasterData.php` 136 baris.

## 3. Beda dari Node dan dari dokumen

- CLAUDE.md (impor DCS) mengira pengaturan Node tidak ada di PostgreSQL; importer ini membaca tabel `settings` (`db.ts:124`) seperti yang ditemukan di audit (K1).

## 4. Konflik dokumen vs kode

- Tidak ada baru.

## 5. Risiko dan yang belum diverifikasi

- **Tidak diuji terhadap PostgreSQL Node yang asli.** Pembaca memakai penunjuk JSON Laravel (`data->nama`) yang diuji hanya di SQLite. Perilakunya di PostgreSQL (`->>`) seharusnya sama tetapi belum terbukti; bentuk data sungguhan (mis. `platforms` bukan larik, tipe angka sebagai teks) belum terlihat.
- `masterData` dibaca utuh ke memori (termasuk `operatorDetails` yang berisi rekening). Tidak pernah disimpan atau dicetak, tetapi ada di memori proses selama impor.
- Platform hanya diambil dari `tenant.platforms` yang tersimpan; fallback kode Node untuk tenant bawaan (`server.ts:1597-1601`) tidak diimpor.
- Perubahan manusia pada baris hasil impor hilang bila di-rollback.
- Nilai kontrak yang dibandingkan hanya diambil dari kolom klasifikasi; kontrak itu sendiri tidak diimpor.
- Kunci tetap `legacy_key` bergantung pada nama untuk platform/kategori/hubungan kerja/tipe pihak: bila Node mengganti nama nilai itu, impor berikutnya menganggapnya baris baru.

## 6. Temuan di luar lingkup

- Demo kontrak lain di kode Node (`GA-VND-2026-0001`, `server.ts:1795`) tidak ada di daftar demo yang Anda sebut, jadi tidak saya kecualikan (hanya memengaruhi laporan nilai tak dikenal).

## 7. Pertanyaan terbuka

1. **Tambahkan `GA-VND-2026-0001` ke daftar contoh yang dikecualikan?** Rekomendasi: ya (config saja). [Keyakinan sedang]
2. **Uji pembaca terhadap salinan PostgreSQL Node (tanpa data pribadi) sebelum dipakai.** Hanya Anda yang punya aksesnya; rekomendasi: jalankan `--dry-run` terhadap salinan itu di lingkungan tes. [Keyakinan tinggi]

## 8. Berkas

- Kode baru: `app/Console/Commands/ImportLegacyMasterDataCommand.php`, `app/Actions/MasterData/{ImportLegacyMasterData,RollbackLegacyMasterData}.php`, `app/Support/MasterData/Legacy/{ImportReport,LegacyReader,LegacyLookupImporter,LegacyDocumentTypeImporter,LegacyPartnerImporter,LegacyEmployeeImporter,LegacyUsageCheck}.php`
- Diubah: `config/master_data.php` (bagian `import`)
- Tes baru: `tests/Support/LegacyMasterDataFixture.php`, `tests/Feature/Actions/MasterData/{ImportLegacyMasterDataTest,ImportLegacyMasterDataCommandTest}.php`
- Dokumen: `docs-tracked/master-data/06b-b6-laporan.md`
