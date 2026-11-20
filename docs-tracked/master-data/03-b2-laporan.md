# 03 — Laporan Blok B2: Policy, Request, Resource, ListSpec, Action

Dibuat 2026-11-20. Tanpa UI, tanpa controller dan route (itu menunggu keputusan di bawah). B3 belum dikerjakan.

## 1. Dikerjakan
- **Enum `MasterList`**: katalog 8 daftar (platform, kategori, jenis dokumen, hubungan kerja, tipe pihak, mata uang, kelompok mitra, mitra). Nama yang tampil ke pengguna ada di `config/master_data.php` (`lists`).
- **Policy** (`app/Policies/MasterData`, satu per model, dikenali otomatis): `viewAny/create/reorder` + `view/update/deactivate/reactivate`. Baris PT lain dijawab **404**, izin kurang **403**, super admin boleh ke semua PT.
- **Action** (`app/Actions/MasterData`): `SaveLookupRecord`, `SaveDocumentType`, `SavePartnerGroup`, `SavePartner`, `DeactivateMasterRecord`, `ReactivateMasterRecord`, `ReorderMasterRecords`. Semuanya berjalan dalam transaksi dan menulis audit log (siapa, kapan, nilai lama/baru), jadi sebagian B5 sudah terpenuhi di sini.
- **Kesamaan PT di tabel penghubung** dijaga di Action oleh `OwnedIds`: kategori pada jenis dokumen, platform pada kelompok mitra, kelompok pada mitra, dan semua id pada urutan.
- **Form Request** (`app/Http/Requests/MasterData`, 11 berkas): otorisasi lewat Policy, PT terkunci ke PT pengguna (super admin wajib memilih), nama/kode unik per PT tanpa membedakan huruf besar/kecil, pesan galat berbahasa Indonesia yang menyebut cara memperbaiki.
- **Resource** (6 berkas): field umum, `can` dari Policy, `usage_count`.
- **ListSpec**: `LookupListSpec` (cari nama/kode, filter status dan asal, urut) + `DocumentTypeListSpec` (filter modul) + `PartnerListSpec` (filter kelompok).
- **Usage** (`Usage::count`, `Usage::withCount`): jumlah pemakaian, satu query untuk seluruh daftar.
- `CLAUDE.md` bagian "Master data" diperbarui.

## 2. P8: izin dan role yang menerimanya (diverifikasi dari `SystemRole::defaultPermissions()`)
| Izin | Role bawaan yang memegangnya | Dipakai untuk |
|---|---|---|
| `settings.manage` | super_admin, **admin**, **it_system** | platform, jenis dokumen, tipe pihak, mata uang, kelompok mitra, mitra, hubungan kerja |
| `templates.manage` | super_admin, admin, **legal** | kategori dokumen eksternal (sama dengan Node) |
| `settings.employment_types` | **hanya super_admin** | hanya hubungan kerja |

Catatan: **IT/System juga memegang `settings.manage`**, jadi ia ikut bisa mengelola platform PT-nya (sama seperti Node, `roles.ts:165`). Rekomendasi P8 menyebut "admin PT"; kalau IT/System tidak boleh, cabut izinnya dari role itu lewat layar Hak Akses. Izin sempit `settings.employment_types` tidak dimiliki role non-super mana pun secara bawaan (di Node juga `defaults: []`), jadi HR/GA belum bisa mengubah hubungan kerja kecuali diberi izin itu.

## 3. Bukti
- Tes baru 117, semuanya lulus: `MasterDataPolicyTest` (66: matriks role × 8 daftar untuk 403, 404 lintas PT, super admin, izin sempit), `SaveMasterDataActionsTest`, `ChangeMasterDataStateTest`, `SaveMasterDataRequestsTest`, `MasterDataListSpecTest`.
- **Kombinasi lintas PT ditolak** (diuji): kategori PT lain pada jenis dokumen (baru dan ubah), platform PT lain pada kelompok, kelompok PT lain pada mitra, baris PT lain pada setiap Action Save (untuk semua 8 daftar), id PT lain pada urutan (8 daftar, seluruh perubahan dibatalkan).
- **Nonaktifkan**: mengembalikan jumlah pemakaian (3 karyawan pada hubungan kerja, 2 mitra pada kelompok), data yang memakai tetap utuh, dua kali nonaktif tidak mengubah apa pun, baris penanda karyawan ditolak.
- Suite penuh: SQLite 1368 tes (1355 lulus, 13 dilewati); PostgreSQL 1368/1368 lulus (dengan `php -d memory_limit=512M`, lihat risiko).
- Pint lulus. PHPStan: 0 error di berkas master data (sisa ±105 error lama di modul DCS, tidak disentuh).
- Ukuran berkas: tertinggi `SaveMasterDataActionsTest.php` 143 baris dan `SaveMasterDataRequest.php` 135 baris; tidak ada yang melebihi 200 (hasil perintah di sesi).

## 4. Beda dari Node dan dari dokumen
- Hanya yang diizinkan yang bisa mengubah: Node membolehkan semua yang login membaca `masterData`, di sini daftar pengelolaan butuh izin (opsi untuk formulir B3 terbuka untuk seluruh pengguna PT).
- Satu aksi "Save" untuk create+update (bukan dua kelas terpisah), dan satu `SaveLookupRecord` dipakai lima daftar berbentuk sama. Tiap daftar tetap punya tabel, Policy, Request dan Resource sendiri; bukan mesin pengaturan generik.
- Kode baris bawaan (`is_system`) tidak bisa diubah supaya `master-data:seed-defaults` tetap mengenalinya; penanda karyawan (`is_employee_key`, `is_employee`) hanya diatur seed/importer, dan barisnya tidak bisa disembunyikan.
- Kode boleh dikosongkan saat membuat: dibuat dari nama. Mata uang wajib kode 3 huruf.
- Tidak ada hapus permanen sama sekali (bukan hanya untuk data terpakai).

## 5. Konflik dokumen vs kode
- `01-audit.md` §5 menyebut `settings.manage` "admin PT"; kode memberinya juga ke it_system (lihat §2).
- Tidak ada konflik baru lainnya.

## 6. Risiko dan yang belum diverifikasi
- **Belum ada controller/route**, jadi Policy dan Request diuji langsung, bukan lewat HTTP. Perilaku terbungkus controller (flash toast, redirect, pengikatan `{record}`) belum teruji.
- `Usage` hanya menghitung master data lain; jenis dokumen, tipe pihak, mata uang, mitra selalu melapor 0 sampai kontrak/dokumen dimigrasi, jadi angka itu belum berarti "tidak dipakai kontrak".
- Karyawan (B4) belum punya Policy/Action; `employees` hanya muncul sebagai penghitung pemakaian hubungan kerja.
- Suite PostgreSQL penuh sekarang melewati batas memori bawaan PHP 128 MB (`composer test:pg` bisa gagal dengan "Allowed memory size exhausted"). Saya menjalankannya dengan `memory_limit=512M`. Belum saya ubah `composer.json`.
- Pengurutan memakai nomor 1..n hanya untuk id yang dikirim; baris yang tidak disebut memakai nomor lamanya, sehingga urutan bisa bentrok kalau layar tidak mengirim seluruh daftar.

## 7. Temuan di luar lingkup
- ±105 error PHPStan lama di kode DCS.
- `composer test:pg` perlu `memory_limit` lebih besar.

## 8. Pertanyaan terbuka
1. **Controller dan route sebelum B3?** Syarat awal "tanpa UI" memisahkan keduanya; controller tipis (tanpa halaman Vue) bisa dibuat sekarang dan diuji HTTP, atau ditunda ke fase UI. Rekomendasi: tunda ke fase UI, karena route perlu halaman Inertia untuk daftar dan form. [Keyakinan sedang]
2. **IT/System boleh mengelola platform?** Lihat §2. Rekomendasi: biarkan (sama seperti Node). [Keyakinan rendah, keputusan organisasi, jadi tidak saya ubah]
3. **Hapus untuk baris yang belum dipakai dan bukan bawaan?** Sekarang tidak ada hapus. Kalau salah ketik, baris cukup disembunyikan tetapi kode dan nama tetap terpakai. Rekomendasi: tambahkan hapus hanya untuk baris buatan sendiri yang pemakaiannya 0. [Keyakinan sedang]
4. **Ubah `composer.json` agar `test:pg` memakai memory_limit lebih besar?** Rekomendasi: ya. [Keyakinan tinggi]
