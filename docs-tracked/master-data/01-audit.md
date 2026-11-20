# 01 — Audit Konfigurasi & Master Data (Fase A)

Dibuat 2026-11-20. Tanpa kode. Dokumen yang dipakai: `docs/master-data/00-peta-sumber.md`, `CLAUDE.md`, `docs/schema/module-map.md`, `docs/auth/02-user-tenant-as-is.md`. `docs/migrasi-karyawan/01-audit.md` **tidak ada**. Semua rujukan Node relatif ke `management-contract/`; Laravel relatif ke `smart-clm/`.

Data produksi Node belum dilihat. Jumlah pemakaian dan nilai nyata menunggu hasil `docs/master-data/queries.sql`. Seed di Fase B memakai nilai yang sudah dicek lewat query itu, bukan nilai default di kode.

---

## 1. Inventaris master data di Node

Semua daftar (kecuali kategori dan perusahaan) tinggal di **satu blob JSON per tenant**: `settings.data.masterData` (`db.ts:124`, `server.ts:1810-1940`). `GET /api/settings` boleh dibaca semua peran yang sudah masuk (`server.ts:2119`). `PUT /api/settings` butuh `settings.manage` (atau `settings.employment_types` untuk satu daftar saja) (`server.ts:2124-2146`).

Menu Konfigurasi (`src/App.tsx:5717-5752`): *Dokumen & Template*: Jenis Dokumen (Semua Modul), Format Nomor Default & Dropdown, Klausul DCS, Klausul Kontrak, Kategori Kontrak. *Master Data*: Data Vendor, Data Karyawan, Mitra/Operator. *Perusahaan & Sistem*: Perusahaan & e-Meterai, Kelola Pengguna, Hak Akses, Kelola Perusahaan.

| # | Entitas (istilah Node) | Kolom | Pengubah (izin Node) | Dibaca oleh | Default di kode | Mock? |
|---|---|---|---|---|---|---|
| E1 | **Perusahaan / PT** (`tenants`) | `id, name, branch, code?, active?, platforms[], registrationCode?, createdAt` (`src/types.ts:1-18`) | super_admin (`server.ts:1227-1297`) | form klasifikasi eksternal & karyawan (langkah 1), token `{Company}` di nomor | tenant `t-01` | t-01 "PT Semesta Digital Terpadu" adalah demo (`auth/02-user-tenant-as-is.md` §2) |
| E2 | **Platform** (`tenant.platforms[]`) | string, maks 50 char, maks 30 per PT (`server.ts:1583-1595`) | super_admin (Kelola Perusahaan) | form eksternal langkah 3, token `{Platform}` | `t-01` → Asmat, Tiketux (`server.ts:1597-1601`); TBK & GKM dari regex nama (`server.ts:7692-7695`) | default t-01 = demo |
| E3 | **Kategori Dokumen Eksternal** (tabel `categories`, juga "kategori klausul" & "folder") | `tenantId, name, sort_order` (PK `tenantId,name`) (`db.ts:120-123`) | `templates.manage` (`server.ts:1984-2026`) | Klausul, Template, Kontrak (`category` string), Anggaran (`BudgetEntry.category`) | `General, Vendor, Employment, NDA, MOU, Rental` (`server.ts:242`); tenant baru + `Customer, Legalitas Perusahaan` (`server.ts:1270`); seed 7 kategori (`server.ts:7680-7683`) | — |
| E4 | **Jenis Dokumen Kontrak** (`masterData.docTypes[]`) | `id, name, code, extraCodes[{label,value}], categories[], appliesTo (external/employee/both), defaultTemplateId, numberMask, retentionYears` (`server.ts:1880-1895, 2063-2089`) | `settings.manage` | wizard kontrak (eksternal langkah 2, karyawan langkah 3), penomoran, template default, retensi arsip | 8 jenis (`server.ts:1886-1894`) + 12 eksternal (`7684-7691`) + 12 karyawan (`7697-7710`) | — |
| E5 | **Kategori Kontrak Karyawan** (`masterData.employmentTypes[]`) | string | `settings.manage` atau `settings.employment_types` (`server.ts:2133-2146`) | form karyawan langkah 2 | `PKWT, PKWTT, Magang / Internship, Harian Lepas, Freelance / Tenaga Ahli Lepas` (`server.ts:1920`) | — |
| E6 | **Departemen / Divisi** (`masterData.departments[]`) | string | `settings.manage` | form DCS (`src/App.tsx:20010-20013`), form karyawan langkah 4 "Divisi" (opsional) | `HRD, GA, HSE, Finance, Legal` (`server.ts:1916`) | — |
| E7 | **Tipe Pihak Kedua** (`partyTypes[]`) | string | `settings.manage` | form kontrak (`party2Type`), penentu modul (`"Karyawan"` = kontrak karyawan, `src/App.tsx:6460`) | `Vendor, Karyawan, Customer / Klien, Mitra / Partner` (`server.ts:1896`) | — |
| E8 | **Mata Uang** (`currencies[]`) | string | `settings.manage` | form kontrak | `IDR, USD, SGD, EUR` (`server.ts:1897`) | — |
| E9 | **Status Rangkap Fisik** (`copyStatuses[]`) | string | `settings.manage` | `ContractCopy.status` (`src/types.ts:288`) | `Disimpan, Dikirim, Di-assign, Diarsipkan` (`server.ts:1913`) | — |
| E10 | **Mitra / Operator** (`masterData.operatorGroups[]`) | grup `{id, name, companies[], platforms[], operators[], operatorDetails{nama → PIC, telp, email, NPWP, alamat, rekening, bank, cabang, PPh%}}` (`operatorDefaults.ts`) | `settings.manage` | form eksternal langkah 4 (opsional), folder Arsip Eksternal level 4 | 3 grup, ±107 operator (`operatorDefaults.ts`) | daftar nyata perusahaan grup (bukan mock) — perlu dicek |
| E11 | **Data Karyawan** (`employees`) | `id, tenantId, name, position, department, salary, startDate` (`src/types.ts:760-768`) | `employees.manage` (`server.ts:2408-2440`) | wizard kontrak karyawan: isi otomatis nama/jabatan/tanggal/gaji (`src/App.tsx:9257-9290`) | `EMP001-003` (`server.ts:1544-1548`) | **mock** |
| E12 | **Data Vendor** (`vendors`) | `name, picName, picPhone, email, npwp, address, bank*, pphRatePercent` (`src/types.ts:770-790`) | `vendors.manage` (`server.ts:2447-2488`) | kontrak (`vendorSnapshot`, `src/types.ts:529`), evaluasi vendor | `VND001-003` (`server.ts:1550-1554`) | **mock** |
| E13 | **Variabel Template** (`variables`) | `key, label, type, description, defaultValue` (`src/types.ts:115-122`) | tidak ada route CRUD (grep `api/variables` = 0) | template & klausul (`{{Variabel}}`) | 11 variabel (`server.ts:1498-1510`) | bawaan kode |
| E14 | Prefiks nomor per kategori (`categoryPrefixes`, `defaultNumberPrefix`) | record/string | `settings.manage` | `defaultNumberPrefix` sebagai cadangan (`server.ts:2667`); `categoryPrefixes` **tidak dibaca** (hanya didefinisikan, `server.ts:1901`) | `GA-AGR` dst. | sisa lama |
| E15 | Format nomor default (`defaultNumberMask`) | string mask | `settings.manage` | penomoran kontrak | `REFERENCE_DEFAULT_MASK` | — |
| E16 | Narasi pembuka per kategori (`categoryOpeningParagraphs`) | record kategori → HTML | `settings.manage` | cetak kontrak (`server.ts:8862, 8925`) | `{}` | — |
| E17 | Penyetuju default per kategori (`categoryApprovalDefaults`) | record kategori → user id[] | `settings.manage` | ajukan persetujuan (`src/App.tsx:7444`) | tidak ada di `defaultSettings` | — |
| E18 | Pengaturan dokumen kontrak | `approvalSlaDays, contractPageMargins, contractWatermark, reminderDefaults, documentTypography` | `settings.manage` | kontrak, pengingat | `server.ts:1840-1930` | — |
| E19 | Identitas perusahaan | `companyName, companyRepresentative, companyRepresentativeTitle, companyAddress, companyLogo*` | `settings.manage` (logo: `server.ts:3315`) | kop kontrak, kop DCS | kosong | — |
| E20 | TTD digital Pihak Pertama | `ttdDigital{dataUrl,...}` | super_admin (`server.ts:2203-2250`) | kontrak | — | — |
| E21 | Hak akses menu/aksi/folder | `rolePermissions, actionPermissions, folderPermissions` | admin | semua | `{}` | — |
| E22 | Sub folder arsip (`sub_folders`), Anggaran (`budgets`), Kode pendaftaran | — | `contract.edit`, `budgets.manage`, `settings.manage` | Arsip, panel anggaran, registrasi | — | — |

### Konflik dokumen ↔ kode

| # | Dokumen | Isi | Kode | Putusan |
|---|---|---|---|---|
| K1 | `CLAUDE.md` (DCS import, `--settings`) | "Node kept them [settingsByTenant] in its JSON db, not in PostgreSQL" | Node menyimpan `settings` di tabel Postgres `settings("tenantId", data jsonb)` dan memuatnya ke `settingsByTenant` (`db.ts:124, 168-172, 228-236`) | Kode Node menang: importer master data **bisa** membaca `settings` langsung dari Postgres. Mungkin ada mode JSON lama (`database.json` ada di repo), belum diverifikasi. Usul: perbaiki kalimat CLAUDE.md (di luar lingkup, lihat §10). |
| K2 | `docs/schema/module-map.md` §3 | "Departemen ... Di Node semuanya string bebas; tidak ada tabel" | Benar, tetapi ada **satu daftar** `masterData.departments` yang dipakai DCS dan form karyawan; DCS Node menyimpan nama itu di `dcs_documents.department_id TEXT` (`dcs/schema.ts:9-10, 83`) | Kode menang; dokumen tidak salah, hanya kurang. |
| K3 | `docs/schema/module-map.md` §1 | "Konfigurasi & Master Data: `/api/settings`, `/tenants`, `/users`, `/registration-code`" | Juga `/api/clause-categories`, `/api/number-companies`, `/api/tenant-platforms`, `/api/modules/*`, `/api/settings/company-logo`, `/api/settings/digital-signature` | Kode menang. |
| K4 | Permintaan Anda (B2) | "Query object (cari, urut, paginasi server-side)" | Laravel tidak punya Query object; polanya `ListSpec` (`app/Support/Listing`, CLAUDE.md "list kit") | Saya pakai `ListSpec` untuk daftar dan kelas `*Options` kecil untuk opsi dropdown. Konfirmasi di P10. |
| K5 | Permintaan Anda (B3) | rantai "PT → hubungan kerja → jenis surat → divisi" | Node: PT → Kategori Kontrak (= hubungan kerja) → Jenis Dokumen → Divisi; **hubungan kerja tidak membatasi jenis surat** dan jenis surat tidak membatasi divisi (`src/App.tsx:1361-1418`). Satu-satunya ketergantungan nyata: eksternal PT → Platform → Mitra/Operator (`src/App.tsx:1218-1232`) | Lihat P5. |

---

## 2. Yang sudah ada di Laravel dan keputusan per entitas

Sudah ada: `tenants` (`name`, `code` unik ≤50, `is_active`, `legacy_id`) (`database/migrations/2026_11_18_125420_create_tenants_table.php`, `..._150856_add_legacy_id_...`); RBAC DB + `Permission` enum yang sudah memuat `settings.manage`, `settings.employment_types`, `templates.manage`, `employees.manage`, `vendors.manage`, `budgets.manage` (`app/Enums/Permission.php:22-32`); `audit_logs` + `RecordAuditLog`; DCS: `dcs_departments` (`tenant_id, code, name, is_active`, unik `tenant_id, lower(code)`, tanpa `sort_order`) (`..._151615_create_dcs_configuration_tables.php:15-25`), `dcs_document_types`, `dcs_numbering_rules`, `dcs_tenant_settings` (nama, alamat, logo, margin, catatan kerahasiaan) (`app/Models/Dcs/TenantSetting.php:13-24`); halaman pengguna & hak akses; menu placeholder Master Data (`app/Support/Navigation/NavigationMenu.php:134-144`).

| Entitas | Keputusan | Alasan | Risiko dua sumber |
|---|---|---|---|
| E1 PT | **(a) reuse `tenants`** | sudah ada, `code` sudah ada | Lihat P1: Node menawarkan **semua** PT ke setiap pengguna |
| E2 Platform | **(c) tabel baru `tenant_platforms`** | daftar di JSON, perlu FK dari kontrak dan nonaktif | — |
| E3 Kategori eksternal | **(c) `contract_categories`** | dipakai klausul, template, kontrak, anggaran; Node memakai nama sebagai kunci | `dcs_clauses` punya kategori sendiri? Tidak — DCS memakai jenis dokumen, jadi aman |
| E4 Jenis dokumen kontrak | **(c) `contract_document_types`** (+ `contract_document_type_categories`, `contract_document_type_codes`) | bidang berbeda dari `dcs_document_types` (applies_to, retensi, mask kontrak); aturan 7 melarang ubah skema DCS | Tinggi bila digabung dengan DCS. Menu Node menyatukan keduanya di satu layar ("Semua Modul") — di Laravel tetap dua tabel, layar bisa menautkan |
| E5 Hubungan kerja | **(c) `employment_types`** | — | — |
| E6 Departemen | **(b) milik DCS → baca lewat kelas baca-saja** (`DepartmentDirectory`) | Node hanya punya satu daftar departemen dan DCS sudah memakainya; tabel kedua = dua sumber | `dcs_departments` tak punya `sort_order`; departemen Node yang belum pernah dipakai dokumen DCS mungkin belum terimpor (importer DCS membuatnya dari dokumen, `FindOrCreateDepartment`). Lihat P4 |
| E7 Tipe pihak | **(c) `party_types`** + kolom `is_employee` | `"Karyawan"` sebagai string ajaib menentukan modul (`src/App.tsx:6460`) | — |
| E8 Mata uang | **(c) `currencies`** (kode ISO) | daftar per tenant di Node | — |
| E9 Status rangkap | **tunda; usul enum di kode** | ini status, bukan referensi; CLAUDE.md: status di kode | — |
| E10 Mitra/Operator | **(c) `partner_groups`, `partners`** (+ pivot ke PT/platform) | daftar 1000/grup di JSON, detail bank | Tinggi: `operatorDetails` mengulang kolom Vendor (PIC, NPWP, rekening). Lihat P6 |
| E11 Karyawan | **(c) `employees` minimal**, hanya bila disetujui (B4) | Lihat P2, P3 | Bisa tumpang tindih dengan `users` (karyawan yang juga pengguna) |
| E12 Vendor | **tunda ke modul Dokumen Eksternal** | bukan dropdown klasifikasi; datanya sensitif (rekening) | — |
| E13 Variabel template | **tunda ke modul Klausul & Template** | bawaan kode, tak ada CRUD | — |
| E14 Prefiks per kategori | **buang** | `categoryPrefixes` tak dibaca; `defaultNumberPrefix` hanya cadangan | — |
| E15-E18 | **tunda** ke modul pemakainya (penomoran kontrak, template, persetujuan) | bukan master data dropdown | penomoran kontrak tidak boleh memakai `NumberAllocator` DCS tanpa keputusan (aturan 7) |
| E19 Identitas perusahaan | **(b) baca `dcs_tenant_settings`** untuk nama/alamat/logo; wakil & jabatan wakil **tunda** | menghindari kop ganda | Tinggi bila tabel baru menyimpan nama/alamat lagi |
| E20-E22 | **tunda / diganti** | TTD digital & arsip punya modul sendiri; hak akses sudah RBAC; registrasi dihapus (CLAUDE.md) | — |

---

## 3. Kebutuhan modul berikutnya

**Dibutuhkan sekarang:**

| Master | Library Klausul & Template | Dokumen Eksternal | Dokumen Karyawan | Bukti |
|---|---|---|---|---|
| PT (tenant) + kode | — | langkah 1, `{Company}` | langkah 1 | `src/App.tsx:1218, 1393`; `src/types.ts:451-453` |
| Platform | — | langkah 3, `{Platform}` | — | `src/App.tsx:1310-1324`; `src/types.ts:455` |
| Kategori eksternal | kategori klausul & template | turunan dari jenis | (`Employment` tetap kunci template karyawan) | `src/types.ts:103-112, 124-131`; `src/App.tsx:6490-6496`; `src/types.ts:569` komentar |
| Jenis dokumen kontrak | `defaultTemplateId` | langkah 2 | langkah 3 | `src/App.tsx:6500-6508` |
| Hubungan kerja | — | — | langkah 2 | `src/App.tsx:1405-1412` |
| Departemen | — | — | langkah 4 (opsional) | `src/App.tsx:1421-1428` |
| Tipe pihak, mata uang | — | form kontrak | tipe pihak "Karyawan" | `src/App.tsx:24992-25183` |
| Mitra/Operator | — | langkah 4 (opsional) | — | `src/App.tsx:1325-1337` |
| Karyawan | — | — | pilih karyawan untuk isi otomatis | `src/App.tsx:9257-9290, 24820-24830` |

**Belum dibutuhkan (tunda):** vendor + evaluasi vendor, anggaran, variabel template (masuk fase template), status rangkap, narasi pembuka per kategori, penyetuju default, tipografi/margin/watermark/SLA/pengingat kontrak, format nomor kontrak, TTD digital, sub folder arsip, hak akses folder, `branch` tenant, kode pendaftaran (dihapus).

---

## 4. Kelemahan skema/data Node

1. **Nama sebagai kunci.** Kontrak menyimpan `category`, `docType`, `platform`, `operator`, `employmentType`, `division`, `party2Type`, `currency` sebagai teks (`src/types.ts:447-455, 571-576`). Ganti nama kategori = ubah massal berdasarkan string (`server.ts:1998-2013`); ganti nama lain (jenis, platform, departemen) **tidak** ikut mengubah kontrak lama.
2. **Penghitung nomor berkunci nama jenis**: `tid|<nama jenis>|tahun|...` (`server.ts:2655-2659`). Ganti nama jenis = urutan mulai dari awal = risiko nomor ganda.
3. **Daftar dalam JSON**: semua `masterData` di satu blob; tanpa FK, tanpa unik, tanpa audit per item (hanya "Update Settings" umum, `server.ts:2182`).
4. **"Lainnya (isi manual)"** di semua langkah klasifikasi (`src/App.tsx:1303, 1314, 1330, 1409, 1416, 1425`): nilai bebas masuk kontrak dan **otomatis menjadi folder arsip & kode nomor**. Ada juga entri bernama "Lainnya" di `docTypes` (`server.ts:1894`).
5. **Hapus permanen**: kategori dihapus bila tak dipakai kontrak, tanpa cek klausul/template/anggaran (`server.ts:2016-2025`); karyawan dan vendor dihapus permanen (`server.ts:2434-2440, 2480`); tidak ada status nonaktif di daftar mana pun.
6. **Tanpa audit**: tambah/ubah/hapus karyawan tidak diaudit; vendor hanya "Create Vendor" (`server.ts:2400-2490`, satu `pushAudit`).
7. **Mass assignment**: `PUT /api/modules/employees/:id` menyebar `req.body` ke rekaman (`server.ts:2429`).
8. **Per tenant vs global tidak konsisten**: kategori/jenis/daftar per tenant, tetapi PT dan grup mitra menunjuk **PT lain** (`operatorGroups.companies: ["GKM"]`, `operatorDefaults.ts`; `/api/number-companies` semua tenant).
9. **Kata "divisi" dipakai untuk tiga hal**: departemen (`masterData.departments`), `division` kontrak karyawan, dan `requestingDivision` yang sebenarnya **role** (`marketing`, `business_partnership`, … `src/roles.ts:23`).
10. **Nilai yang di-hardcode untuk logika**: `"Employment"` (kategori karyawan), `"Karyawan"/"Employee"` (tipe pihak), `"General"` (kategori cadangan) (`src/App.tsx:6460, 6488-6493`).
11. **Default ganda**: daftar default ditulis di server **dan** di klien sebagai cadangan (`src/App.tsx:6426-6435`) — bisa berbeda.

---

## 5. Data sensitif dan izin yang terlalu longgar

| Temuan | Bukti | Usulan kontrol |
|---|---|---|
| **Gaji semua karyawan** terbaca oleh setiap pengguna yang masuk | `GET /api/modules/employees` hanya `requireAuth` (`server.ts:2403-2406`) | Tidak menyimpan gaji di master (P2). Daftar karyawan hanya untuk `employees.manage`; pemakai modul kontrak karyawan mendapat opsi `{id, nama, jabatan}` saja. |
| **Rekening bank, NPWP vendor** terbaca semua pengguna | `GET /api/modules/vendors` hanya `requireAuth` (`server.ts:2442-2445`) | (Saat modul vendor) Resource terpisah: kolom bank hanya untuk `vendors.manage`. |
| **Detail mitra (rekening, NPWP)** ikut `GET /api/settings` yang dibaca semua peran | `server.ts:2119-2122` + `operatorDetails` | Detail mitra di tabel sendiri, hanya untuk pengelola; opsi dropdown hanya `{id, nama}`. |
| **Daftar semua PT** (lintas tenant) ke setiap pengguna | `/api/number-companies` (`server.ts:1237-1247`) | Default Laravel: hanya PT milik pengguna. Lihat P1. |
| Matriks izin lewat `settings.manage` bisa disetel IT | ditambal di `server.ts:2155-2165` | Sudah diganti RBAC DB. |
| Perubahan master tanpa nilai lama/baru | `server.ts:2182` | `RecordAuditLog` per perubahan dengan `before/after`. |
| NIK | Tidak ada di `EmployeeData` (`src/types.ts:760-768`). Hanya muncul sebagai nomor identitas pihak yang diketik bebas di kontrak (`party1IdLabel`, `src/types.ts:589`) | Master karyawan tanpa NIK (P3). NIK di kontrak urusan modul Dokumen Karyawan. |

Izin di Laravel (usul, reuse enum yang ada): baca opsi aktif = semua pengguna perusahaan itu; kelola PT/platform = `settings.manage` (lihat P8); kategori eksternal = `templates.manage` (sama seperti Node); jenis dokumen kontrak, tipe pihak, mata uang, mitra = `settings.manage`; hubungan kerja = `settings.manage` atau `settings.employment_types`; karyawan = `employees.manage`. Lintas PT → 404.

---

## 6. Skema target (usulan, belum dibuat)

Aturan umum: bigint id; `timestamptz`; `created_by_id`/`updated_by_id` → `users` `nullOnDelete`; `tenant_id` → `tenants` `restrictOnDelete`; `is_active` (nonaktif, tidak dihapus); `sort_order smallint`; `code` + `name`; `legacy_key` (string Node asli, untuk impor ulang); unik `(tenant_id, lower(code))` dan `(tenant_id, lower(name))`.

**Satu keputusan pola (P7):** CLAUDE.md menyebut lookup "`tenant_id` null = default bawaan". Pola itu membuat perusahaan **tidak bisa menyembunyikan** satu nilai bawaan (nonaktif berlaku untuk semua). DCS memilih **menyalin default ke tiap tenant** (`SeedDcsTenantDefaults`). Usul saya mengikuti DCS: semua baris milik tenant (`tenant_id` NOT NULL), `is_system` menandai yang berasal dari seed, kode diambil dari config `config/master_data.php` (bukan hardcode di Action).

```
tenants (ada)                                  ┌───────────────────────────────┐
  id, name, code, is_active, legacy_id ────────┤ tenant_platforms              │
        │                                      │ id, tenant_id FK restrict,    │
        │                                      │ code, name, sort_order,       │
        │                                      │ is_active, is_system,         │
        │                                      │ legacy_key, created/updated_by│
        │                                      └──────────────┬────────────────┘
        ├── contract_categories                               │
        │     id, tenant_id, code, name, sort_order,          │
        │     is_active, is_system, is_employee_key*,         │
        │     legacy_key ...                                  │
        │          ▲                                          │
        │          │ (pivot) contract_document_type_categories│
        │          │   document_type_id FK cascade,           │
        │          │   category_id FK restrict, PK (both)     │
        ├── contract_document_types                           │
        │     id, tenant_id, code, name, applies_to           │
        │     (varchar→enum: external|employee|both),         │
        │     retention_years null, sort_order, is_active,    │
        │     is_system, legacy_key ...                       │
        │       └── contract_document_type_codes              │
        │             id, document_type_id FK cascade,        │
        │             label, value, sort_order                │
        ├── employment_types (id, tenant_id, code, name, sort_order, is_active, is_system, legacy_key)
        ├── party_types (… + is_employee bool)
        ├── currencies (… code ISO 3 huruf, name)
        ├── partner_groups (id, tenant_id, name, sort_order, is_active, legacy_key)
        │     ├── partner_group_platforms (group_id FK cascade, platform_id FK restrict)
        │     └── partners (id, tenant_id, partner_group_id FK restrict, name, sort_order,
        │                   is_active, legacy_key)   [detail PIC/bank: tunda, P6]
        ├── employees (B4, bila disetujui: id, tenant_id, employee_number null,
        │              name, position, dcs_department_id FK restrict null,
        │              employment_type_id FK restrict null, start_date, user_id FK null
        │              nullOnDelete, is_active, legacy_key) — tanpa gaji, tanpa NIK
        └── dcs_departments (ada, hanya dibaca)
```
\* `is_employee_key`: menggantikan string ajaib `"Employment"`; maksimal satu per tenant (indeks unik parsial). Sama untuk `party_types.is_employee`.

Kolom yang sengaja **tidak** dibuat: `default_template_id` dan `number_mask` di jenis dokumen (menunggu tabel template dan keputusan penomoran kontrak); detail bank mitra; gaji.

Indeks: `(tenant_id, is_active, sort_order)` di tiap lookup untuk daftar opsi; unik di atas; FK di semua kolom `_id`. Pemakaian dihitung lewat FK dari tabel pemakai (kontrak dsb.) — sampai modul itu ada, jumlah pemakaian = relasi antar-master saja (mis. jenis ↔ kategori, mitra ↔ platform, karyawan ↔ departemen).

**Rencana migrasi data dari Node** (B6, `php artisan master-data:import-legacy`, pola sama dengan `dcs:import-legacy`):
1. Baca `tenants` (platform), `categories`, `settings.data.masterData` (`docTypes`, `employmentTypes`, `partyTypes`, `currencies`, `operatorGroups`), `employees` (tanpa `salary`) dari koneksi `legacy`.
2. Per tenant, satu transaksi; kunci impor = `legacy_key` (nama asli atau `id` jenis Node) → aman diulang.
3. Laporkan nilai yang **dipakai kontrak tetapi tidak ada di daftar** (hasil "Lainnya"), duplikat beda huruf, dan entri "Lainnya".
4. `--dry-run` (rollback setelah laporan), `--tenant=t-01`, `--rollback` (hapus baris `legacy_key` milik tenant itu **hanya bila belum dipakai** di Laravel).
5. Departemen: tidak diimpor ke tabel baru; nilai Node yang belum ada di `dcs_departments` dilaporkan (P4).

---

## 7. Query baca-saja

Di `docs/master-data/queries.sql`. Mengembalikan nilai distinct dan jumlah pemakaian per entitas, per tenant. Tidak menyentuh gaji, nama karyawan, NIK, atau isi dokumen.

---

## 8. UX untuk pengguna awam

| Istilah Node | Masalah | Usulan |
|---|---|---|
| Kategori Kontrak / Folder / Kategori Klausul | satu daftar, tiga nama | **Kategori Dokumen Eksternal** |
| Kategori Kontrak (Karyawan) | bentrok dengan di atas | **Status Hubungan Kerja** (bantuan: "PKWT, PKWTT, Magang, …") |
| Jenis Dokumen (Semua Modul) | bercampur DCS | **Jenis Surat & Perjanjian** (Eksternal / Karyawan); DCS tetap "Jenis Dokumen Internal" |
| Tipe Pihak Kedua | jargon | **Jenis Mitra Kontrak** |
| Mitra / Operator, Platform | belum dijelaskan hubungannya | **Platform** ("layanan/produk perusahaan, mis. Tiketux") dan **Mitra** ("perusahaan rekanan per platform") |
| Divisi / Departemen / Divisi peminta | tiga makna | **Departemen** saja untuk master; "divisi peminta" tetap peran |
| extraCodes / {Codes} | teknis | **Kode tambahan di nomor** |
| appliesTo | teknis | **Dipakai di**: Eksternal / Karyawan / Keduanya |
| Nonaktifkan | — | "Sembunyikan dari pilihan baru. Data lama tetap memakai nilai ini." |

Alur termudah: satu halaman **Master Data** dengan tab per daftar (`PageTabs`), tiap tab = list kit + dialog tambah/ubah (pola `departments/Index.vue`), urutan dengan tombol naik/turun (bukan seret, ramah sentuh), aksi "Nonaktifkan" dengan konfirmasi yang menyebut jumlah pemakaian. Tidak ada isian "Lainnya": bila pilihan tidak ada, pesan "Belum ada di daftar? Minta admin menambahkannya di Master Data" (+ tautan bila punya izin).

---

## 9. Pertanyaan terbuka

**P1. Siapa "PT" di form?** Node menampilkan **semua perusahaan** di aplikasi (lintas tenant) kepada setiap pengguna, dan grup mitra merujuk PT lain (`server.ts:1237-1247`, `operatorDefaults.ts`). Ini bertentangan dengan aturan 6.
Rekomendasi: PT = tenant pengguna saja (super admin: pilih tenant), tanpa daftar PT lain. Bila satu tim legal memang menerbitkan kontrak untuk beberapa PT dalam satu grup, itu perlu desain izin lintas tenant terpisah. [Keyakinan sedang — nama TBK & GKM di seed menunjukkan grup perusahaan yang nyata; hasil query Q1/Q2 akan menunjukkan apakah kontrak benar-benar memakai `companyId` ≠ `tenantId`.]

**P2. Gaji karyawan.** Node mengisi variabel kontrak `Salary` dan `contractValue = gaji × 12` dari master (`src/App.tsx:9278-9285`).
Rekomendasi: master **tanpa gaji**; gaji diketik per kontrak di wizard (tersimpan sebagai isi kontrak, dibatasi izin kontrak). [Keyakinan tinggi — sesuai instruksi Anda "tanpa gaji"; konsekuensinya satu isian manual tambahan.]

**P3. Perlukah B4 (tabel `employees`) sekarang?** Node hanya memakainya untuk isi otomatis nama/jabatan/tanggal; datanya mock di seed.
Rekomendasi: ya, minimal (nama, jabatan, departemen, hubungan kerja, tanggal mulai, nomor karyawan opsional, `user_id` opsional), **tanpa NIK** karena master karyawan Node tidak punya NIK (hanya isian identitas pihak di kontrak, `src/types.ts:589`). [Keyakinan sedang — tergantung hasil Q8: bila produksi hampir kosong, bisa ditunda.]

**P4. Departemen.** Reuse `dcs_departments` lewat kelas baca-saja (tanpa ubah skema DCS) berarti tidak ada `sort_order` dan departemen dikelola di layar DCS. Nilai Node yang belum terimpor DCS dilaporkan, tidak dibuat otomatis.
Rekomendasi: reuse baca-saja; urutan = nama. [Keyakinan tinggi — Node hanya punya satu daftar departemen.]

**P5. Opsi bergantung.** Di Node yang benar-benar bergantung hanya PT → Platform → Mitra, dan Jenis ↔ Kategori (eksternal). Hubungan kerja tidak membatasi jenis surat.
Rekomendasi: B3 meniru Node apa adanya (PT → platform → mitra; jenis per modul/kategori; hubungan kerja dan departemen berdiri sendiri). Bila Anda ingin "hubungan kerja → jenis surat" (mis. Perjanjian Magang hanya untuk Magang), itu aturan bisnis baru dan butuh pivot tambahan. [Keyakinan sedang.]

**P6. Mitra vs Vendor.** Detail mitra (PIC, NPWP, rekening) sama dengan kolom vendor.
Rekomendasi: Fase B hanya nama mitra + grup + platform; detail ditunda dan nanti disatukan dengan Vendor (satu tabel pihak eksternal). [Keyakinan sedang — hasil Q6 menunjukkan apakah `operatorDetails` terisi di produksi.]

**P7. Pola default lookup.** CLAUDE.md: `tenant_id` null = default bawaan. Usul: salin default ke tiap tenant (seperti DCS), supaya tiap perusahaan bisa menonaktifkan nilai bawaan.
Rekomendasi: salin per tenant; perbarui CLAUDE.md sesudah Anda setuju. [Keyakinan tinggi — DCS sudah membuktikan pola ini.]

**P8. Siapa mengelola platform PT?** Node: super admin saja.
Rekomendasi: admin perusahaan (`settings.manage`) untuk PT-nya sendiri, super admin untuk semua. [Keyakinan rendah — keputusan organisasi.]

**P9. Izin terpisah untuk master data?** Node memakai `settings.manage` yang juga mengelola pengguna.
Rekomendasi: tetap reuse izin yang ada (tanpa izin baru) agar matriks lama (`role_permissions.csv`) tetap cocok. [Keyakinan sedang.]

**P10. Daftar & opsi.** Pakai `ListSpec` (pola proyek) untuk halaman daftar dan kelas `App\Support\MasterData\*Options` untuk opsi dropdown, bukan "Query object" baru.
Rekomendasi: ya. [Keyakinan tinggi — CLAUDE.md "list kit".]

**P11. Kategori "Employment" dan jenis bernama "Lainnya".** Node menyembunyikan "Employment" dari daftar eksternal dan punya jenis "Lainnya".
Rekomendasi: "Employment" menjadi kategori bertanda `is_employee_key` (tidak tampil di eksternal); jenis "Lainnya" diimpor tetapi dinonaktifkan dan dilaporkan. [Keyakinan sedang.]

**P12. Mata uang dan tipe pihak per perusahaan atau global?** Node: per perusahaan.
Rekomendasi: per perusahaan (disalin dari default). [Keyakinan sedang.]

## 10. Temuan di luar lingkup (tidak dikerjakan)

- `CLAUDE.md` (DCS import): kalimat "Node kept them in its JSON db, not in PostgreSQL" bertentangan dengan `db.ts:124` (K1).
- `docs/` di-ignore git, jadi dokumen audit ini tidak tersimpan di repo.
- `docs/endpoints_all.csv` dan `docs/data/endpoints_all.csv` berbeda isi.
- Banyak dokumen auth/DCS usang (lihat `00-peta-sumber.md` §1).
- Penghitung nomor kontrak Node berkunci nama jenis (§4 butir 2) — penting untuk desain penomoran kontrak nanti.
