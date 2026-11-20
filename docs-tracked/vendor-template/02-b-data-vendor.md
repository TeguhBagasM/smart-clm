# 02 — Fase B: Data Vendor (B1–B6)

Tanggal: 2026-11-20. Keputusan yang dipakai: semua rekomendasi `01-audit.md` disetujui ("Setuju semua"): Q-V1 tabel `vendors` terpisah dari mitra, Q-V2 halaman hanya `vendors.manage`, Q-V3 simpan semua kolom Node dan batasi per izin, Q-V4 nomor rekening dienkripsi, Q-V5 nama unik per PT, Q-V6 tanpa kolom jenis vendor/customer, Q-V7 tanpa impor/ekspor CSV di layar.

## 1. Dikerjakan

**B1 — skema.** Migration `2026_11_20_100900_create_vendors_table.php` (satu tabel, reversible; diuji `migrate` → `migrate:rollback --step=1` → `migrate` di `smart_clm_test`). Kolom: `tenant_id` (FK `restrictOnDelete`), `name`, `npwp` (angka saja, 16), `address`, `pic_name`, `pic_phone`, `email`, `bank_name`, `bank_branch`, `bank_account_name`, `bank_account_number` (text, cast `encrypted`), `pph_rate_percent numeric(5,2)`, `is_active`, `legacy_key`, `created_by_id`/`updated_by_id` (FK `nullOnDelete`), `timestamptz(6)`.

| Kolom | Indeks | Alasan | Query (bukti di `02-b1-explain.txt`, 50.000 baris / 20 PT) |
|---|---|---|---|
| `(tenant_id, lower(name))` | UNIQUE `vendors_tenant_name_unique` | nama unik per PT (Q-V5); melayani FK `tenant_id`; urutan default daftar `ORDER BY lower(name)` dibaca langsung dari indeks | Q1 halaman default: Index Scan, 0,044 ms; Q3 filter status: 0,053 ms; Q5 cek nama ganda: 0,013 ms; Q2 hitung total: Bitmap Index Scan 0,83 ms |
| `(tenant_id, legacy_key) WHERE legacy_key IS NOT NULL` | UNIQUE parsial | importer idempoten | Q6: Index Only Scan, 0,005 ms |
| `npwp`, `pic_name`, `email` | tidak diindeks | dicari `like '%…%'` (B-tree tak terpakai); Q4 cari tetap 0,086 ms karena difilter per PT lewat indeks nama | — |
| `is_active` | tidak diindeks | selalu bersama `tenant_id` (Q3) | — |
| `bank_account_number` | tidak diindeks | terenkripsi, tidak pernah dicari (filter "rekening belum diisi" hanya `IS NULL`) | — |
| `created_by_id`, `updated_by_id` | tidak diindeks | tidak pernah difilter/join (pola `partners`) | — |

Catatan jujur: Node hanya punya 3 vendor mock; tabel produksi kemungkinan ratusan baris, jadi pada data nyata beda indeks tidak akan terasa. EXPLAIN di atas membuktikan rencana query, bukan kebutuhan kinerja.

**B2 — backend.** Model `App\Models\MasterData\Vendor` (`#[Hidden]` nomor rekening, `formattedNpwp()`, `bankAccountTail()`, daftar `SENSITIVE`), factory, relasi `Tenant::vendors()`. `VendorPolicy` (`vendors.manage`; PT lain 404, izin kurang 403). `SaveVendorRequest` (NPWP 15/16 angka, telepon, email, rekening 5-30 angka, PPh 0-100 dengan koma atau titik, nama unik; nomor rekening **hanya tulis**: kosong = tetap, `clear_bank_account_number` = hapus). `SaveVendor`, `SetVendorActive` (transaksi + audit; mengembalikan jumlah pemakaian lewat `Usage`, 0 sampai kontrak dimigrasi). `VendorAuditSnapshot`: audit hanya menyimpan nama, alamat, status, `legacy_key`, dan **nama** kolom sensitif yang berubah (`changed_sensitive`), tidak pernah nilainya. `VendorResource` (kolom sensitif hanya untuk `vendors.manage`; nomor rekening tidak pernah dikirim, hanya 4 angka terakhir). `VendorListSpec` (cari nama/kontak/email/NPWP dengan atau tanpa titik; filter status dan kelengkapan; urut nama/terakhir diubah/dibuat; label dari config). `DependentOptions::vendors()` + `GET master-data/options/vendors` (nama saja, untuk semua pengguna PT). Semua teks di `config/vendors.php`.

**B3 — importer.** `php artisan vendors:import-legacy --tenant=t-01 [--dry-run] [--rollback]`: `LegacyReader::vendors()`, `LegacyVendorImporter`, `ImportLegacyVendors`, `RollbackLegacyVendors`. Per tenant, satu transaksi, cocok lewat id Node (`legacy_key`), baris yang sudah diimpor tidak ditimpa (perubahan di Laravel tetap), mock `VND001-003` dilewati. Baris bermasalah (nama kosong/ganda, NPWP bukan 15/16 angka, PPh di luar 0-100, email salah, terlalu panjang) = galat; **impor sungguhan ditolak bila dry run tidak bersih** (tidak ada `--force`). Laporan hanya memuat id Node dan nama kolom. Diuji hanya dengan fixture sintetis `tests/Support/LegacyVendorFixture.php`; tidak pernah dijalankan ke DB lain.

**B4 — HTTP.** `VendorController` (index/store/update/deactivate/reactivate), route `master-data.vendors.*` (digabung dengan route karyawan dalam satu loop), binding `{record}`.

| Route | Izin | Data |
|---|---|---|
| `GET /master-data/vendors` | `vendors.manage`; tanpa izin → halaman 403 ramah | `master-data/Vendors`: `items` (VendorResource: tanpa nomor rekening penuh), `list`, `info` (teks dari config), `company`, `companies` (super admin) |
| `POST /master-data/vendors`, `PUT …/{record}` | `vendors.manage`; PT lain 404 | simpan; toast; audit tanpa nilai sensitif |
| `PATCH …/{record}/deactivate`, `/reactivate` | `vendors.manage`; PT lain 404 | sembunyikan/tampilkan; toast |
| `GET /master-data/options/vendors` | semua pengguna PT | `[{value, label}]` vendor aktif, nama saja |

Tidak ada route hapus.

**B5 — UI.** `pages/master-data/Vendors.vue`, `components/master-data/{VendorTable,VendorSheet}.vue`, `fields/VendorFields.vue`, `lib/vendors.ts`, `types/vendors.ts`. Daftar pakai list kit (cari, filter, urut, paginasi server, state di query string, kosong + kosong-karena-pencarian dari kit). Form = Sheet (layar penuh di ponsel) dua bagian dari config; galat per kolom dengan cara memperbaiki; isian tetap saat galat/jaringan putus (`MasterFormSheet`); tombol mati saat mengirim. Sembunyikan memakai dialog dampak (`useMasterList`, teks dari config). Status ditaruh di bawah nama vendor supaya tombol aksi tetap terlihat di 768. Menu "Data Vendor" diaktifkan (`NavigationMenu`, terlihat bila `viewAny` Vendor). `impactDialog`/`useMasterList`/`tableColumns` kini menerima tipe yang lebih sempit agar bisa dipakai halaman vendor (perilaku halaman lama tidak berubah).

## 2. Bukti

- Tes baru: `VendorPolicyTest` (8), `VendorActionsTest` (7), `ImportLegacyVendorsTest` (7), `VendorControllerTest` (19 termasuk 8 kasus validasi), `NavigationMenuTest` (+7), `tests/js/master-data/vendors.test.ts` (4).
- Suite penuh (`php -d memory_limit=512M`): PostgreSQL 1603/1603 lulus (7543 asersi); SQLite 1590 lulus + 13 dilewati (tes khusus PostgreSQL), 0 gagal.
- `vue-tsc` bersih; `vp lint` dan `vp fmt --check` bersih untuk berkas yang saya sentuh; Pint lulus; `npm run build` sukses; 104 tes JS lulus.
- PHPStan: berkas vendor bersih. Sisa temuan di kode lama (tidak saya ubah): `FormOptionsController:47`, `DependentOptions::map`, `LegacyReader::categories/employees`, `ListPage`, `Usage::count`, `NavigationMenu:57/61`.
- Ukuran berkas (semua php/vue/ts yang dibuat/diubah): terbesar `routes/web.php` 192, `NavigationMenu.php` 188, `SaveVendorRequest.php` 153; tidak ada yang > 200.
- Browser (pane bawaan, `localhost:8000`, akun demo Legal): 1280, 768 (tabel tanpa gulir samping, `scrollWidth = clientWidth = 718`), 375 (kartu, tombol lebar penuh, Sheet layar penuh), dark mode di 375. Alur: tambah dengan NPWP salah → pesan "NPWP harus 15 atau 16 angka…" dan isian tetap → perbaiki → toast "Vendor … ditambahkan", rekening tampil `Bank Uji •••• 7890`; ubah → kolom rekening kosong dengan bantuan "Tersimpan: rekening berakhiran 7890…", simpan tanpa mengubah rekening → tetap; sembunyikan → dialog dampak → status "Disembunyikan". Dicek: HTML halaman tidak memuat nomor rekening penuh.

## 3. Beda dari Node

- Halaman hanya untuk `vendors.manage` (Node: `GET` vendor terbuka untuk semua pengguna, termasuk rekening).
- Nomor rekening terenkripsi, tidak pernah dikirim balik ke browser; diganti/hapus secara eksplisit.
- Tidak ada hapus permanen (Node: hapus permanen tanpa audit); sembunyikan dengan konfirmasi.
- Validasi server (Node: hanya nama wajib di klien); nama unik per PT.
- Semua ubah/sembunyikan diaudit tanpa nilai sensitif (Node: hanya "Create Vendor").
- Cari, filter, urut, paginasi server (Node: kartu tanpa cari).

## 4. Konflik dokumen vs kode

Tidak ada yang baru selain K1-K4 di `01-audit.md`.

## 5. Risiko dan yang belum diverifikasi

- Rotasi `APP_KEY` membuat nomor rekening tak terbaca tanpa re-enkripsi (konsekuensi Q-V4). Perlu dicatat di runbook deploy.
- Importer belum pernah membaca DB Node sungguhan (hanya fixture); bentuk JSON produksi diasumsikan sama dengan `src/types.ts:770-784`.
- Jumlah pemakaian selalu 0 sampai modul kontrak ada (`vendors.usage_covers_contracts=false`, pemberitahuan tampil di halaman).
- Saat memuat ulang daftar, kit menampilkan redup (opacity), bukan skeleton; ini perilaku list kit bersama yang tidak saya ubah.
- Dev DB (`smart_clm`) sudah dimigrasi untuk tabel `vendors` dan berisi satu vendor uji "CV Solusi Uji" (disembunyikan) dari cek browser.
- Belum dicek di ponsel fisik. Skenario cek manual: (1) masuk sebagai Legal → Master Data › Data Vendor; (2) tambah vendor di ponsel, isi NPWP salah, pastikan pesan muncul dan isian tidak hilang; (3) matikan internet lalu simpan: pesan "Belum bisa menyimpan…" dan isian tetap; (4) ubah vendor: kolom rekening kosong, teks bantuan menyebut 4 angka terakhir; (5) sembunyikan dan tampilkan lagi; (6) masuk sebagai HR/GA: menu tidak ada, URL langsung → halaman "tidak punya akses".

## 6. Temuan di luar lingkup

- `Employees.vue` menulis teks placeholder dan kosong langsung di Vue (bukan dari config) — pola lama.
- `StatusBadge` dan tombol `RowActions` memakai teks tetap di Vue — pola lama, dipakai bersama.
- Temuan PHPStan lama di bagian 2.

## 7. Pertanyaan terbuka

1. Hapus vendor uji "CV Solusi Uji" dari dev DB? Tidak ada route hapus; perlu SQL manual oleh Anda. Rekomendasi: biarkan (disembunyikan). [Keyakinan tinggi]
2. Saat vendor dipakai kontrak kelak, apakah salinan data vendor di kontrak (Node `vendorSnapshot`) ikut menyimpan rekening? Rekomendasi: ya, terenkripsi, diputuskan di modul kontrak. [Keyakinan sedang]

## Berkas

Dibuat: `database/migrations/2026_11_20_100900_create_vendors_table.php`, `database/factories/MasterData/VendorFactory.php`, `app/Models/MasterData/Vendor.php`, `app/Policies/MasterData/VendorPolicy.php`, `app/Http/Requests/MasterData/SaveVendorRequest.php`, `app/Http/Resources/MasterData/VendorResource.php`, `app/Http/Controllers/MasterData/VendorController.php`, `app/Actions/MasterData/{SaveVendor,SetVendorActive,ImportLegacyVendors,RollbackLegacyVendors}.php`, `app/Support/MasterData/VendorAuditSnapshot.php`, `app/Support/MasterData/Legacy/LegacyVendorImporter.php`, `app/Support/Listing/VendorListSpec.php`, `app/Console/Commands/ImportLegacyVendorsCommand.php`, `config/vendors.php`, `resources/js/pages/master-data/Vendors.vue`, `resources/js/components/master-data/{VendorTable,VendorSheet}.vue`, `resources/js/components/master-data/fields/VendorFields.vue`, `resources/js/lib/vendors.ts`, `resources/js/types/vendors.ts`, `tests/Feature/Policies/VendorPolicyTest.php`, `tests/Feature/Actions/MasterData/{VendorActionsTest,ImportLegacyVendorsTest}.php`, `tests/Feature/Http/Controllers/MasterData/VendorControllerTest.php`, `tests/Support/LegacyVendorFixture.php`, `tests/js/master-data/vendors.test.ts`, `docs-tracked/vendor-template/{02-b-data-vendor.md,02-b1-explain.txt}`.

Diubah: `routes/web.php`, `app/Models/Concerns/HasMasterData.php`, `app/Support/MasterData/{DependentOptions,Usage}.php`, `app/Support/MasterData/Legacy/LegacyReader.php`, `app/Http/Controllers/MasterData/FormOptionsController.php`, `app/Support/Navigation/NavigationMenu.php`, `resources/js/{lib/masterData.ts,composables/useMasterList.ts,types/index.ts}`, `tests/Feature/Support/NavigationMenuTest.php`, `docs-tracked/vendor-template/PROGRESS.md`.
