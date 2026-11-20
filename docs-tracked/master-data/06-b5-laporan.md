# 06 — Laporan Blok B5: verifikasi audit log

Dibuat 2026-11-20. Audit sudah ditulis di tiap Action (B2, B4); blok ini memverifikasi, menambah satu event yang belum ada, dan menutup celah tes.

## 1. Dikerjakan

- Tabel aksi → event → nilai lama/baru → tes (di bawah).
- **Satu penambahan kode**: `SeedTenantMasterData` sekarang menulis `master_data.defaults.seeded` (tanpa pelaku, karena sistem) bila sebuah run menambah sesuatu dan bukan dry-run. Run yang tidak menambah apa pun tidak menulis audit.
- Tes baru `MasterDataAuditTest` (menutup celah di bawah).
- Verifikasi siapa yang bisa membaca audit (poin 2 dari jawaban B4).

## 2. Tabel audit

Semua event ditulis lewat `RecordAuditLog` di dalam transaksi Action yang sama; `tenant_id` = PT baris; `actor_id` = pengguna.

| Aksi (Action)                                                                            | Event                                            | Sebelum                                            | Sesudah                     | Dicakup tes                                                                               |
| ---------------------------------------------------------------------------------------- | ------------------------------------------------ | -------------------------------------------------- | --------------------------- | ----------------------------------------------------------------------------------------- |
| Buat/ubah platform, kategori, hubungan kerja, tipe pihak, mata uang (`SaveLookupRecord`) | `master_data.<daftar>.created` / `.updated`      | `null` / kolom baris (tanpa timestamp dan pembuat) | kolom baris                 | ya, 5 daftar (`MasterDataAuditTest`)                                                      |
| Buat/ubah jenis dokumen (`SaveDocumentType`)                                             | `master_data.document-types.created/.updated`    | kolom + `category_ids` + `extra_codes`             | sama                        | ya, termasuk relasi                                                                       |
| Buat/ubah kelompok mitra (`SavePartnerGroup`)                                            | `master_data.partner-groups.created/.updated`    | kolom + `platform_ids`                             | sama                        | ya, termasuk relasi                                                                       |
| Buat/ubah mitra (`SavePartner`)                                                          | `master_data.partners.created/.updated`          | kolom                                              | kolom                       | ya                                                                                        |
| Sembunyikan (`DeactivateMasterRecord`)                                                   | `master_data.<daftar>.deactivated`               | `is_active: true`                                  | `is_active: false`          | ya, 8 daftar. Tidak menulis bila sudah tersembunyi                                        |
| Tampilkan lagi (`ReactivateMasterRecord`)                                                | `master_data.<daftar>.reactivated`               | `is_active: false`                                 | `is_active: true`           | ya, 8 daftar                                                                              |
| Urutkan (`ReorderMasterRecords`)                                                         | `master_data.<daftar>.reordered` (subjek = PT)   | `{order: [id lama]}`                               | `{order: [id baru]}`        | ya, 8 daftar                                                                              |
| Buat/ubah karyawan (`SaveEmployee`)                                                      | `master_data.employees.created/.updated`         | `null` / kolom                                     | kolom                       | ya (B4)                                                                                   |
| Sembunyikan/tampilkan karyawan (`SetEmployeeActive`)                                     | `master_data.employees.deactivated/.reactivated` | `is_active` lama                                   | baru                        | ya (B4)                                                                                   |
| Nilai bawaan (`SeedTenantMasterData`, `master-data:seed-defaults`)                       | `master_data.defaults.seeded` (tanpa pelaku)     | `null`                                             | `{added: {daftar: [nama]}}` | ya (baru): dry-run tidak menulis, run yang menambah menulis satu, run ulang tidak menulis |

`Usage`, `DependentOptions`, `ClassificationGuard`, dan `EmployeeDirectory` hanya membaca, jadi tidak diaudit.

## 3. Bukti

- `MasterDataAuditTest`: 27 tes (dataset 8 daftar × buat/ubah dan × sembunyikan/tampilkan/urut), lulus. Seluruh blok Action master data: 82 tes lulus.
- **Transaksi yang sama**: 9 skenario (buat platform, jenis dokumen dengan kode tambahan, kelompok, karyawan; ubah nama; sembunyikan; sembunyikan karyawan; urutkan; seed) mensimulasikan kegagalan menulis audit. Hasilnya semua perubahan ikut dibatalkan (jumlah baris tabel sama, nama/`is_active`/`sort_order` tidak berubah, tidak ada baris audit). Artinya audit memang di dalam transaksi, bukan sesudahnya.
- Pint lulus. Ukuran: `SeedTenantMasterData.php` 129 baris, `MasterDataAuditTest.php` 149 baris.

## 4. Siapa yang bisa membaca audit (poin 2)

- `grep` pada `app/`, `routes/`, dan `resources/js/`: **tidak ada controller, route, halaman, atau izin yang membaca `audit_logs`**. Satu-satunya jejak adalah menu "Audit Trail" di `NavigationMenu.php:129`, yaitu penampung "Sedang dikembangkan" yang hanya terlihat oleh super admin (gerbang `viewModulesInDevelopment`) dan tidak membuka data.
- `Permission` tidak punya izin audit.
- Jadi saat ini audit hanya bisa dibaca lewat akses langsung ke database. Tidak terlalu longgar; justru belum ada jalur baca. Karena nama karyawan, jabatan, dan nomor karyawan masuk ke nilai lama/baru, **ketika layar Audit Trail dibangun, izin bacanya harus dipilih dengan sengaja**. Saya tidak membuat atau mengubah izin apa pun.

## 5. Beda dari Node dan dari dokumen

- Node hanya menulis "Update Settings" tanpa nilai (`server.ts:2182`); tidak mengaudit karyawan. Di sini setiap perubahan punya event dan nilai lama/baru.

## 6. Konflik dokumen vs kode

- Tidak ada.

## 7. Risiko dan yang belum diverifikasi

- Audit karyawan menyimpan nama dan jabatan (data pribadi). Belum ada jalur baca, jadi risikonya tertunda ke saat layar audit dibuat.
- Event penghapusan tidak ada karena tidak ada hapus.
- `audit_logs` append-only hanya sebagai konvensi (tidak ada pemicu database seperti pada `dcs_document_events`); tidak saya ubah.

## 8. Temuan di luar lingkup

- `audit_logs` tidak dilindungi dari UPDATE/DELETE di tingkat database (CLAUDE.md menyebut "append-only; never update rows" sebagai aturan kode).

## 9. Pertanyaan terbuka

1. **Izin baca Audit Trail kelak?** Rekomendasi: izin baru khusus (mis. `audit.view`) yang tidak otomatis ikut `settings.manage`, karena audit memuat data pribadi. Tidak saya buat. [Keyakinan sedang: ini kebijakan izin, jadi keputusan Anda]
2. **Lindungi `audit_logs` dengan pemicu database seperti `dcs_document_events`?** Rekomendasi: ya, di luar lingkup master data. [Keyakinan sedang]

## 10. Berkas

- Diubah: `app/Actions/MasterData/SeedTenantMasterData.php`
- Baru: `tests/Feature/Actions/MasterData/MasterDataAuditTest.php`, `docs-tracked/master-data/06-b5-laporan.md`, `docs-tracked/master-data/PROGRESS.md`
