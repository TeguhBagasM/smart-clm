# 08 — Laporan F0: controller tipis dan route

Dibuat 2026-11-20. Belum ada halaman Vue: controller me-render `master-data/Index`, `master-data/List`, dan `master-data/Employees`, yang dibangun di F1–F2. Sampai itu, route HTML ini belum bisa dibuka di browser (komponen belum ada); JSON `options` dan semua aksi tulis sudah bisa dipakai dan diuji.

## 1. Dikerjakan

- **9 controller tipis** (`app/Http/Controllers/MasterData`): `MasterDataIndexController`, `MasterListController` (daftar + urutan), `LookupRecordController` (5 daftar sederhana), `DocumentTypeController`, `PartnerGroupController`, `PartnerController`, `MasterRecordStateController` (sembunyikan/tampilkan), `EmployeeController`, `FormOptionsController`. Tiap method: otorisasi lewat Policy atau Form Request, panggil Action/ListSpec, kembalikan Resource atau `Inertia::render`. Tidak ada logika bisnis di controller.
- **Pendukung** (`app/Support/MasterData`): `MasterDataCatalog` (indeks), `ListPage` (baris, meta, teks, opsi form), `ActingCompany::find`. `ResolvesCompany` (PT dan toast) di `Controllers/MasterData/Concerns`.
- **Semua teks dari config**: `config/master_data.php` kini memuat `lists` (nama, kegunaan, kolom, teks bantuan per kolom), `groups` (urutan dan isi indeks), `messages` (toast), `usage_covers_documents`. Tidak ada daftar nilai bisnis di controller atau (nanti) di Vue.
- **Route** `master-data.*` (19 route, grup `auth`), `{record}` diikat di `routes/web.php`. Tidak ada route hapus. `master-data:seed-defaults` **tidak** dibuatkan endpoint (lihat pertanyaan 1).
- Form Request: `SaveLookupRequest` kini dipakai langsung untuk lima daftar (daftar dibaca dari parameter rute `list`); kelima subkelas lama tetap ada.

## 2. TABEL PERMUKAAN: route → izin → data yang dikembalikan

Semua route butuh login. 403 = izin kurang; 404 = baris PT lain atau daftar tidak dikenal. PT pengguna selalu dipakai; hanya super admin memilih PT dengan `tenant_id`.

| Route (nama)                                                                                                    | Izin                                                                | Data yang dikembalikan / diubah                                                                                                                                                                                                                                                                                                                                                        |
| --------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET /master-data` (`master-data.index`)                                                                        | login; item disaring per daftar lewat Policy `viewAny`              | Inertia `master-data/Index`: `groups[{key,title,description,items[{key,label,description,href,count}]}]`, `company`, `companies` (hanya super admin), `needs_company`. `count` = baris aktif PT. Tanpa izin apa pun: `groups: []`                                                                                                                                                      |
| `GET /master-data/options/{kind}` (`.options`)                                                                  | login; `employees` butuh `employees.manage`                         | JSON `{data:[{value,label,code?}]}` hanya baris aktif PT. `partners?platform_id=`, `categories?module=`, `document-types?module=&category_id=`. `employees`: `{value,label,position}`. 422 bila parameter kurang atau super admin belum memilih PT                                                                                                                                     |
| `GET /master-data/employees` (`employees.index`)                                                                | `employees.manage`                                                  | `master-data/Employees`: `items` (EmployeeResource: tanpa gaji/NIK), `list`, `info`, `options{departments,employment_types,users}` (`users` hanya bila pengguna boleh melihat pengguna, `settings.manage`; selain itu `null`), `company`                                                                                                                                               |
| `POST /master-data/employees`, `PUT …/{record}`                                                                 | `employees.manage`                                                  | simpan karyawan; toast; audit                                                                                                                                                                                                                                                                                                                                                          |
| `PATCH …/employees/{record}/deactivate`, `/reactivate`                                                          | `employees.manage`                                                  | sembunyikan/tampilkan; toast                                                                                                                                                                                                                                                                                                                                                           |
| `POST /master-data/document-types`, `PUT …/{record}`                                                            | `settings.manage`                                                   | simpan jenis dokumen + kategori + kode tambahan                                                                                                                                                                                                                                                                                                                                        |
| `POST /master-data/partner-groups`, `PUT …/{record}`                                                            | `settings.manage`                                                   | simpan kelompok + platform                                                                                                                                                                                                                                                                                                                                                             |
| `POST /master-data/partners`, `PUT …/{record}`                                                                  | `settings.manage`                                                   | simpan mitra                                                                                                                                                                                                                                                                                                                                                                           |
| `POST /master-data/{list}`, `PUT …/{record}` untuk `platforms`, `employment-types`, `party-types`, `currencies` | `settings.manage` (hubungan kerja juga `settings.employment_types`) | simpan baris                                                                                                                                                                                                                                                                                                                                                                           |
| idem untuk `categories`                                                                                         | `templates.manage`                                                  | simpan kategori                                                                                                                                                                                                                                                                                                                                                                        |
| `GET /master-data/{list}` (`lists.index`) untuk 8 daftar                                                        | Policy `viewAny` daftar itu (lihat 07 §2)                           | `master-data/List`: `items` (Resource daftar itu dengan `usage_count` dan `can`), `list` (cari, filter, urut, paginasi), `info` (label, kegunaan, kolom, bantuan, `usage_covers_documents`, `usage_notice`), `options` (modul/kategori untuk jenis dokumen; platform untuk kelompok; kelompok untuk mitra), `company`. Super admin tanpa `tenant_id`: dialihkan ke indeks dengan toast |
| `PUT /master-data/{list}/order`                                                                                 | Policy `reorder` daftar itu                                         | simpan urutan seluruh daftar (`ids`); id PT lain → 422, tidak ada yang berubah                                                                                                                                                                                                                                                                                                         |
| `PATCH /master-data/{list}/{record}/deactivate`, `/reactivate`                                                  | Policy `deactivate`/`reactivate` baris                              | sembunyikan/tampilkan; toast memuat jumlah pemakaian; baris penanda karyawan ditolak (422)                                                                                                                                                                                                                                                                                             |

Tidak ada route yang mengembalikan gaji, NIK, PT lain, atau daftar karyawan tanpa `employees.manage`. Tidak ada `DELETE` (405).

## 3. Bukti

- 86 tes HTTP baru (`tests/Feature/Http/Controllers/MasterData`): indeks per role dan hitungan per PT; 8 daftar × daftar/403/404; cari, filter, urut, paginasi lewat query string; usage dalam satu pass; opsi form; urutan sukses, gagal, dan 403; 5 daftar × tambah/ubah (toast, audit); validasi dengan pesan perbaikan; 404 lintas PT untuk ubah/sembunyikan/tampilkan di 8 daftar; kategori/platform/kelompok PT lain ditolak (422); toast dengan jumlah pemakaian; tidak ada DELETE; karyawan (403 untuk legal/it_system/manajemen/viewer, 404 lintas PT, pesan nomor ganda dan akun terpakai, payload tanpa gaji/NIK); opsi JSON per jenis, hanya aktif dan PT sendiri.
- Suite penuh: PostgreSQL 1543/1543; SQLite 1530 lulus, 13 dilewati (`memory_limit=512M`). PHPStan: 0 temuan di berkas master data dan route. Pint lulus.
- Pemeriksaan ukuran (semua berkas php/vue/ts yang berubah atau baru): tidak ada yang melebihi 200 baris. Terbesar: `routes/web.php` 188 (berkas bersama), `config/master_data.php` 183, `ImportLegacyMasterDataTest.php` 154.

## 4. Beda dari Node dan dari dokumen

- Node mengirim seluruh pengaturan ke semua pengguna; di sini setiap route dibatasi Policy dan PT.
- Hapus tidak ada; Node punya hapus permanen untuk kategori, karyawan, vendor.

## 5. Konflik dokumen vs kode

- Tidak ada baru.

## 6. Risiko dan yang belum diverifikasi

- **Halaman Vue belum ada**: route HTML akan error di browser sampai F1 membuatnya. Tes memakai `inertia.testing.ensure_pages_exist=false` dan harus menghapusnya setelah halaman ada.
- Perilaku di browser (toast, redirect `back()`, penanganan galat) belum dilihat; hanya teruji lewat tes HTTP.
- `Usage` belum menghitung dokumen/kontrak: sinyal `usage_covers_documents=false` dan `usage_notice` dikirim agar UI jujur.
- Super admin memilih PT lewat `?tenant_id=` (GET) atau isian `tenant_id` (tulis); UI harus meneruskannya.
- Daftar `users` di form karyawan hanya untuk pemegang `settings.manage`; HR/GA (tanpa izin itu) tidak melihat pilihan akun. Ini sengaja (tidak melebarkan akses data pengguna), tetapi mungkin tidak yang HR harapkan.

## 7. Temuan di luar lingkup

- Tidak ada baru. Lama: `memory_limit` di `composer test`/`test:pg`; ±105 temuan PHPStan lama di kode DCS.

## 8. Pertanyaan terbuka

1. **Endpoint penyebaran nilai bawaan (`seed-defaults`)?** Tidak dibuat karena belum ada izin yang jelas. Opsi: izin baru khusus, atau hanya super admin lewat perintah. Rekomendasi: tetap perintah artisan (super admin/operator), tanpa endpoint. [Keyakinan sedang: kebijakan izin]
2. **Izin baca Audit Trail** (dari B5) sebelum layar audit dibuat. [Keyakinan sedang]
3. **Boleh HR/GA memilih akun pengguna** pada form karyawan? Butuh melihat daftar pengguna PT (nama). Rekomendasi: tidak untuk sekarang. [Keyakinan rendah: menyangkut data pribadi, keputusan Anda]
4. **`memory_limit` di `composer.json`.** [Keyakinan tinggi: ya]

## 9. Berkas

- Controller baru: `app/Http/Controllers/MasterData/{MasterDataIndexController,MasterListController,LookupRecordController,DocumentTypeController,PartnerGroupController,PartnerController,MasterRecordStateController,EmployeeController,FormOptionsController}.php`, `…/Concerns/ResolvesCompany.php`
- Pendukung baru: `app/Support/MasterData/{MasterDataCatalog,ListPage}.php`
- Diubah: `routes/web.php`, `config/master_data.php`, `app/Enums/MasterData/MasterList.php` (label dari config), `app/Support/MasterData/ActingCompany.php` (`find`), `app/Http/Requests/MasterData/{SaveMasterDataRequest,SaveLookupRequest,ReorderMasterDataRequest}.php`, `CLAUDE.md` (di-ignore git)
- Tes baru: `tests/Feature/Http/Controllers/MasterData/{MasterDataIndexControllerTest,MasterListControllerTest,MasterRecordControllerTest,EmployeeControllerTest,FormOptionsControllerTest}.php`, `tests/Feature/Http/Requests/MasterData/MasterDataRequestAuthorizationTest.php`
- Dokumen: `docs-tracked/master-data/{06-b5-laporan,06b-b6-laporan,07-backend-final,08-f0-laporan,PROGRESS}.md`
