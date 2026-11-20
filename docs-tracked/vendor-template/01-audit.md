# 01 — Audit: Data Vendor dan Klausul & Template (Fase A)

Tanggal: 2026-11-20. Fase A tanpa kode. Sumber: kode Laravel + `CLAUDE.md`, kode Node `../management-contract` (commit `73acb13`), dokumen `docs-tracked/master-data/*` sebagai petunjuk.
Kondisi data: tidak ada akses ke DB Node produksi. Semua nilai bawaan di bawah berasal dari kode dan **belum diverifikasi dengan data produksi**.

---

## A1. Data Vendor

### A1.1 Inventaris Node

| Aspek | Temuan | Bukti |
|---|---|---|
| Penyimpanan | Tabel PostgreSQL `vendors (id TEXT PK, data JSONB, seq)`; satu baris JSON per vendor, ada `tenantId` | `db.ts:72, 111` |
| Bentuk data | `id, tenantId, name, picName, picPhone, email, npwp, address, bankAccountNumber, bankAccountName, bankName, bankBranch, pphRatePercent` | `src/types.ts:770-784` |
| Halaman | Konfigurasi › Master Data › "Data Vendor" (tab `vendor`), terpisah dari "Mitra / Operator" (tab `operator`) dan "Data Karyawan" | `src/App.tsx:5737-5741` |
| Daftar | Kartu 2 kolom: nama, "PIC: nama · email", "NPWP", alamat; tombol ubah & hapus. Tanpa cari, filter, urut, paginasi | `src/App.tsx:17982-18005` |
| Form | Modal: Nama Vendor/Perusahaan*, Nama PIC, No. WhatsApp PIC, Email, NPWP, Alamat; bagian "Info Rekening & Pajak": No. Rekening, Nama Rekening, Bank, Cabang, PPh Dipotong (%) (dibatasi 0-100 di klien) | `src/App.tsx:25691-25746` |
| Validasi | Hanya "Nama vendor wajib diisi" di klien; server tanpa validasi | `src/App.tsx:12545`; `server.ts` POST vendors |
| Route | `GET /api/modules/vendors` (semua pengguna login), `POST/PUT/DELETE` (`vendors.manage`) | `server.ts` blok "INTEGRATED MODULES" (±2427-2483) |
| Siapa mengubah | `vendors.manage`: super_admin, admin, legal | `tests/Fixtures/role_permissions.csv` |
| Audit | Hanya "Create Vendor"; ubah & hapus tidak diaudit | `server.ts` POST vendors |
| Hapus | Hapus permanen (filter array) | `server.ts` DELETE vendors |
| Pemakai | (1) Wizard kontrak eksternal: pilih vendor → isi `party2Name/Address/IdNumber(NPWP)` dan variabel `VendorName, Address, VendorNPWP, VendorPPhRate, VendorPICName, VendorPICPhone, VendorEmail, VendorBank*` (`src/App.tsx:9302-9335`, select di `24840-24860`); (2) Registrasi dokumen arsip: tautan opsional ke master vendor (`26545-26560`); (3) Kontrak menyimpan `vendorSnapshot` (salinan, bukan rujukan hidup) (`src/types.ts:883-900`); (4) Evaluasi Kinerja Vendor per kontrak (`vendorEvaluation`, `src/types.ts:786-800`) — hanya bila kontrak punya `vendorSnapshot` | |
| Impor/ekspor | Tidak ada | grep `api/modules/vendors` |
| Nilai bawaan | 3 vendor mock `VND001-003` (nama, PIC, email, NPWP, alamat; tanpa bank) | `server.ts:1550-1554` |
| "Vendor/Customer" | Daftar yang sama dipakai untuk pihak Customer (label berganti sesuai kategori kontrak); tidak ada kolom jenis | `src/App.tsx:9306-9311, 24846` |

Mitra/Operator (Node `operatorGroups[].operators` + `operatorDetails`) punya kolom detail **sama persis** dengan vendor (`operatorDefaults.ts:14-18`). Di Laravel hanya nama mitra yang dipindah (P6).

### A1.2 Yang sudah ada di Laravel

| Bagian | Isi | Relevan untuk vendor? |
|---|---|---|
| `partners`, `partner_groups`, `partner_group_platforms` | Nama mitra per grup/platform, tanpa kontak/bank (komentar migration, P6) | Bukan vendor. Mitra = daftar nama operator untuk klasifikasi arsip (bisa ribuan per PT). |
| `SavePartner`, `PartnerPolicy` (`settings.manage`), `PartnerListSpec`, halaman Daftar Pilihan `master-data/List` | Pola lengkap: ListSpec, Action + audit, nonaktif dengan jumlah pemakaian, Sheet form, kartu di HP | Pola yang ditiru, bukan tabel yang dipakai ulang. |
| `Permission::VendorsManage` ("Kelola data vendor", grup "Data & Anggaran") | Sudah ada di katalog dan matriks seed | Dipakai tanpa izin baru. |
| `LegacyReader`, `Legacy*Importer`, `ImportLegacyMasterData`/`RollbackLegacyMasterData`, `ImportReport` | Importer master data (dry-run, per tenant, legacy_key) | Pola importer vendor. |
| `Usage` + `master_data.usage_covers_documents=false` | Hitung pemakaian; dokumen belum dihitung | Pemakaian vendor oleh kontrak = 0 sampai modul kontrak ada. |
| Menu | `NavigationMenu.php:141` `soon('vendor', 'Data Vendor', 'store')` | Diaktifkan di B5. |

**Keputusan audit:** tercakup = pola (Policy, ListSpec, Action, audit, importer, UI kit). **Kurang** = tabel `vendors` sendiri (belum ada sama sekali), Action/Request/Resource/Policy/ListSpec vendor, importer vendor, halaman. **Murni UI** = tidak ada; semua butuh backend.

### A1.3 Usulan skema `vendors` (dibuat di B1 setelah disetujui)

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint | |
| `tenant_id` | FK tenants, `restrictOnDelete` | NOT NULL |
| `name` | varchar(200) | wajib |
| `npwp` | varchar(32) null | disimpan dalam bentuk angka saja (15/16 digit), ditampilkan berformat |
| `address` | text null | |
| `pic_name` | varchar(150) null | data pribadi |
| `pic_phone` | varchar(30) null | data pribadi |
| `email` | varchar(254) null | |
| `bank_name`, `bank_branch` | varchar(100) null | |
| `bank_account_name` | varchar(150) null | |
| `bank_account_number` | text null, **cast `encrypted`** (usul Q-V4) | tidak bisa dicari/diindeks bila dienkripsi |
| `pph_rate_percent` | numeric(5,2) null, 0-100 | |
| `is_active` | boolean | nonaktif, bukan hapus |
| `legacy_key` | varchar(100) null | id Node (`VND...`) |
| `created_by_id`, `updated_by_id` | FK users `nullOnDelete` | |
| `created_at`, `updated_at` | timestamptz | |

Indeks:

| Kolom | Indeks | Alasan | Query |
|---|---|---|---|
| `(tenant_id, lower(name))` | UNIQUE | nama tidak ganda per PT (usul Q-V5); juga melayani FK `tenant_id` dan urut nama | `where tenant_id=? order by name`; cek ganda di Request |
| `(tenant_id, legacy_key) where legacy_key is not null` | UNIQUE parsial | importer idempoten | importer `updateOrCreate` |
| `(tenant_id, npwp) where npwp is not null` | biasa | cari vendor berdasar NPWP (ListSpec `search`) dan peringatan NPWP ganda | `where tenant_id=? and npwp like ?%` |
| `is_active` | tidak diindeks | filter aktif selalu bersama `tenant_id`; daftar per PT kecil (Node: puluhan) | — |
| `email`, `pic_name`, `address` | tidak diindeks | dicari `ilike %..%` (indeks B-tree tak terpakai); tabel kecil | — |
| `bank_account_number` | tidak diindeks | terenkripsi | — |
| `created_by_id`, `updated_by_id` | tidak diindeks | tidak pernah difilter/join di daftar (pola sama dengan `partners`) | — |

Tabel diperkirakan kecil (Node: 3 mock; produksi tak diketahui). EXPLAIN ANALYZE akan dijalankan di B1 pada data uji 50.000 baris dan dilaporkan terus terang bila beda tak terlihat.

### A1.4 Data sensitif dan kontrol akses (usul)

| Data | Sifat | Usul |
|---|---|---|
| Nomor rekening, nama rekening, bank, cabang | keuangan | Disimpan (dipakai variabel kontrak `VendorBank*`). Hanya terlihat oleh `vendors.manage`. Nomor rekening dienkripsi di DB dan **disamarkan** di daftar (`•••• 1234`), utuh hanya di form ubah. |
| NPWP | identitas pajak badan (bisa NPWP orang pribadi bila vendor perorangan) | Hanya `vendors.manage` di halaman; tidak di payload pilihan (`options`). |
| Nama/telepon/email PIC | data pribadi | Hanya `vendors.manage`. |
| PPh (%) | data pajak | Hanya `vendors.manage`. |
| Nama, alamat | umum | Boleh di `options` untuk pengguna PT (kelak dipakai form kontrak). |

Aturan teknis: dua Resource (`VendorResource` lengkap untuk pengelola, `VendorOptionResource` hanya `id, name`); tidak ada kolom sensitif di `audit_logs` (audit menyimpan nama kolom yang berubah, bukan nilainya — beda dari master data lain, karena nilai sensitif); tidak pernah dikirim ke AI; tes memastikan payload tanpa kolom sensitif untuk yang tak berhak (pola tes "tanpa gaji/NIK").

---

## A2. Klausul & Template

### A2.1 Inventaris Node

**Struktur data**

| Entitas | Kolom | Bukti |
|---|---|---|
| Klausul (`clauses`, JSONB per baris) | `id, tenantId, title, content, category (string), tags[], isMandatory, isProtected, version` | `src/types.ts:103-113`, `db.ts:64` |
| Variabel (`variables`, PK **`key` global**, bukan per PT) | `key, label, type ("string"\|"number"\|"date"), description, defaultValue` | `src/types.ts:115-122`, `db.ts:65` |
| Template (`templates`) | `id, tenantId, name, description, category, clauseIds[] (rujukan hidup ke klausul), requiredVariables[] (kunci bebas), parties[] (label), openingParagraph?, closingParagraph?, addendumRecitalParagraph?, version?, updatedAt?, updatedByName?` | `src/types.ts:124-159` |
| Narasi pembuka per kategori | `settings.categoryOpeningParagraphs: {kategori: teks}`; kosong = `DEFAULT_PREAMBLE_TEMPLATE`; `Template.openingParagraph` menimpa keduanya | `server.ts:1921-1927`, `src/App.tsx:13519-13522` |
| Kategori | `categories (tenantId, name, sort_order)`; sudah dimigrasi sebagai `contract_categories` | master-data E3 |

**Sintaks variabel:** `{{Key}}`, regex klien `/\{\{([^}]+)\}\}/` (kunci apa saja selain `}`), tanpa tipe ditegakkan saat render. Nilai disubstitusi apa adanya (HTML di-escape untuk konten kaya), kosong tampil `[Key]` merah (`src/App.tsx:2793-2796, 13384-13440`). Isian otomatis dari form kontrak lewat alias (`companyname, vendorname, contractvalue, startdate, address, …`, `src/App.tsx:9011-9031`); sisanya wajib diketik manual di wizard (`9076-9085`). Token di toolbar editor: 8 (`CLAUSE_TOKENS`, `src/App.tsx:7133-7142`); variabel bawaan: 11 (`server.ts:1498-1510`); vendor menambah `VendorNPWP, VendorBank*` dll. tanpa definisi variabel.

**Isi pasal:** HTML dari editor WYSIWYG buatan sendiri (`RichTextEditor`, contenteditable). Tag diizinkan `p, br, b, strong, i, em, u, s, span, div, ul, ol, li, h3, h4, font, table/thead/tbody/tr/td/th, img, a, hr, blockquote` + atribut `style, color, size, src, href, …` (`src/App.tsx:2789`). Disanitasi **hanya di klien** (DOMPurify); server menyimpan apa adanya (`server.ts` POST/PUT clauses). Pasal lama berupa teks/markdown ringan (`**tebal**`). Ekspor Word: `clause-html.ts` (HTML → WordprocessingML), `template-docx.ts` (pasal = content control terkunci).

**Versi:** klausul diubah di tempat, `version+1` setiap simpan. Template diubah di tempat; `version+1` hanya bila `clauseIds, parties, opening/closing/recital` berubah, diaudit "Ubah Redaksi Template" (`server.ts:2361-2399`). Mengubah **isi** klausul tidak menaikkan versi template.

**Pemakaian oleh dokumen (salin vs rujuk):** campuran.
- Saat kontrak dibuat, pasal template **disalin** ke kontrak (`{id, title, content, order}`) (`src/App.tsx:9038-9061`) → teks pasal kontrak tidak berubah bila library berubah.
- `templateSnapshot` hanya menyalin nama, versi, paragraf pembuka/penutup/recital (`server.ts:2703-2716`); kontrak lama tanpa snapshot jatuh ke template hidup.
- Kebijakan wajib/terlindungi dibaca **hidup** dari library berdasarkan id klausul (OnlyOffice, `server.ts:5811-5816`).
- `requiredVariables` disalin sebagai kunci kosong ke `contract.variables`.

**Hak akses:** baca klausul/template: super_admin, admin, legal, **hr_ga** (`server.ts:1974, 2325`, `src/roles.ts:47`); ubah: `templates.manage` (super_admin, admin, legal). Klausul `isProtected` hanya boleh diubah peran admin/legal/super_admin (daftar peran ditulis di kode, `server.ts:2293`) — praktis sama dengan pemegang `templates.manage`. Hapus klausul/template permanen; hapus klausul tidak diaudit dan tidak dicek pemakaian (template bisa merujuk id yang hilang).

**Halaman Node** (`src/App.tsx:23140-23920`): panel kiri kategori dengan jumlah; tab "Klausul | Template"; cari teks; kartu klausul (badge Wajib/Terlindungi); form klausul inline (judul, kategori, tag, editor kaya + token, centang wajib/proteksi, "Simpan ke Clause Library"); kotak "Tulis klausul dengan AI"; form template: nama, kategori, deskripsi, "Penyusun Pasal (drag & drop)", "Variabel Template" (deteksi otomatis dari isi pasal + ketik manual), paragraf pembuka/penutup/recital adendum (opsional).

**AI:** di library hanya `POST /api/ai/write-clause` (deskripsi → `{title, content, tags, reasoning}`, `server.ts:7831-7853`; prompt pengguna disisipkan langsung). "Saran AI" (`/api/ai/suggest-template`, `server.ts:7399-7431`, tombol di `src/App.tsx:24759-24768`) ada di **wizard kontrak**, bukan di modul template.

**Impor/ekspor:** tidak ada untuk library. OnlyOffice plugin "sisipkan-pasal" membaca library (backlog, OnlyOffice di luar lingkup).

**Nilai bawaan:** 8 klausul `cls-1..8` (`server.ts:1414-1495`), 11 variabel (`1498-1510`), 3 template `tmp-1..3` (PKWT/Employment, Vendor SLA, NDA) (`1513-1540`), kategori sudah di master data. Disalin ke tenant `t-01` saja (`db.ts:347`).

### A2.2 Inventaris Laravel

| Bagian | Isi | Keputusan |
|---|---|---|
| `dcs_clauses` (+ `dcs_clause_doc_types`, `dcs_document_type_default_clauses`) | Klausul DCS: `title, content text (teks polos), category varchar, tags jsonb, is_mandatory, is_active`; terhubung ke jenis dokumen DCS | **Tidak dipakai ulang** (aturan: DCS hanya dibaca, tak diubah). Beda kebutuhan: tanpa variabel, tanpa proteksi, kategori bebas, terikat jenis DCS. |
| Editor teks | Tidak ada editor kaya; hanya `components/ui/textarea`. Tidak ada tiptap/quill/DOMPurify di `package.json`; tidak ada HTML sanitizer di composer | Lihat Q-T1. |
| `useReorder` + `ReorderList` | Drag + tombol naik/turun, rollback saat gagal | Dipakai untuk urutan pasal. |
| `StringListEditor`, `FormActions`, `PageTabs`, `ToggleActive`, `ActionDialog`, list kit | Komponen DCS config | Dipakai ulang. |
| `contract_categories` (+ `is_employee_key`), `DocumentModule` | Kategori & modul | Template merujuk kategori (FK). |
| `app/Support/Ai` (`LlmManager`, `StructuredGenerator`, Gemini/DeepSeek) | Klien AI Laravel | Dipakai bila Saran AI disetujui (Q-T7). |
| Menu | `NavigationMenu.php:139` `soon('klausul-template', …)` | Diaktifkan di Fase D. |

**Risiko dua sumber kebenaran dengan DCS:** dua perpustakaan klausul bernama mirip. Mitigasi: nama menu/halaman membedakan ("Klausul & Template" untuk kontrak; DCS tetap "Pengaturan Dokumen › Klausul"), tidak ada sinkronisasi, tidak ada FK silang, `audit_logs` memakai awalan `contract_templates.*` vs `dcs.*`.

### A2.3 Skema target (C1, usul)

```
tenants 1─* contract_clauses ─* (salinan) contract_template_clauses *─1 contract_template_versions
contract_categories 1─* contract_clauses
contract_categories 1─* contract_templates 1─* contract_template_versions
contract_template_versions 1─* contract_template_parties
contract_template_versions 1─* contract_template_version_variables *─1 contract_variables *─1 tenants
contract_templates.current_version_id ─> contract_template_versions (versi terbit terbaru)
```

| Tabel | Kolom utama | FK & onDelete | Alasan |
|---|---|---|---|
| `contract_clauses` | `tenant_id, contract_category_id, title(200), body text, is_mandatory, is_protected, revision int, sort_order, is_active, legacy_key, created_by_id, updated_by_id, timestamptz` | tenant `restrict`, kategori `restrict`, user `nullOnDelete` | Perpustakaan hidup; boleh diubah (`revision+1`); tidak pernah dihapus. Tag Node dibuang (Q-T6). |
| `contract_templates` | `tenant_id, contract_category_id, module (DocumentModule: external/employee), name(200), description, current_version_id null, is_active, legacy_key, created_by_id, updated_by_id` | tenant/kategori `restrict`; `current_version_id` → versions `nullOnDelete` (FK ditambah setelah tabel versi) | Identitas template; `module` mengikuti kategori karyawan (`is_employee_key`) atau eksternal. |
| `contract_template_versions` | `contract_template_id, version_no, status (TemplateVersionStatus: draft/published/retired, varchar), opening_paragraph, closing_paragraph, addendum_recital, published_at, published_by_id, created_by_id, updated_by_id` | template `restrict`; user `nullOnDelete` | **Versi terbit tidak bisa diubah** (dijaga Action + model; usul juga trigger PostgreSQL seperti `dcs_document_events`). Mengubah = membuat draf versi baru dari versi terbit. |
| `contract_template_clauses` | `contract_template_version_id, position, source_clause_id null, source_revision, title, body, is_mandatory, is_protected` | versi `cascadeOnDelete` (hanya draf yang boleh dihapus), klausul sumber `nullOnDelete` | **Salinan** teks saat ditambahkan ke draf; versi terbit membeku. UI draf menandai "pasal di perpustakaan sudah diperbarui" bila `source_revision` < `revision`. |
| `contract_template_parties` | `contract_template_version_id, position, label(150)` | versi `cascade` | Pengganti `parties[]`. |
| `contract_variables` | pola daftar pilihan: `tenant_id, code (kunci {{…}}), name (label), value_type (VariableType: text/number/money/date), description, default_value, source_field null (kunci isian otomatis dari form kontrak, daftar di config), sort_order, is_active, is_system, legacy_key` | tenant `restrict` | Disalin per PT (P7); tipe bertipe, bukan EAV. |
| `contract_template_version_variables` | `contract_template_version_id, contract_variable_id, is_required, position` | versi `cascade`, variabel `restrict` | Variabel yang diminta versi itu. |

Status dan transisi (di kode, enum + tabel transisi): `draft → published` (Terbitkan; versi terbit sebelumnya → `retired`, `current_version_id` pindah, satu transaksi + kunci baris template); `published → retired` hanya lewat penerbitan versi baru; template dinonaktifkan dengan `is_active=false` (tidak muncul di form kontrak, versi tetap utuh). Satu draf per template (indeks unik parsial).

Urutan pasal: `position` di `contract_template_clauses`, disimpan utuh lewat `ReorderTemplateClauses` (hanya draf).

Indeks (usul, akan dibuktikan di C1):

| Kolom | Indeks | Alasan | Query |
|---|---|---|---|
| `contract_clauses (tenant_id, lower(title))` | UNIQUE | judul tak ganda per PT; melayani FK tenant & urut judul | daftar perpustakaan, cek ganda |
| `contract_clauses (contract_category_id)` | biasa | FK + filter kategori | ListSpec filter kategori, `Usage` kategori |
| `contract_clauses (tenant_id, legacy_key)` parsial | UNIQUE | importer | — |
| `contract_templates (tenant_id, lower(name))` | UNIQUE | nama tak ganda; FK tenant | daftar template |
| `contract_templates (contract_category_id)` | biasa | FK + filter kategori; opsi per kategori | ListSpec, kelak opsi kontrak |
| `contract_template_versions (contract_template_id, version_no)` | UNIQUE | nomor versi; melayani FK | riwayat versi |
| `contract_template_versions (contract_template_id) where status='draft'` | UNIQUE parsial | satu draf | buat draf |
| `contract_template_clauses (contract_template_version_id, position)` | UNIQUE | urutan; FK | isi versi |
| `contract_template_clauses (source_clause_id)` | biasa | FK + hitung pemakaian klausul | `Usage` klausul, tanda "diperbarui" |
| `contract_template_version_variables (version_id, variable_id)` | PK | FK + unik | — |
| `…_version_variables (contract_variable_id)` | biasa | FK `restrict` dicek saat nonaktif + pemakaian | `Usage` variabel |
| `contract_variables (tenant_id, lower(code))`, `(tenant_id, lower(name))` | UNIQUE | pola daftar pilihan | — |
| `body`, `description` | tidak diindeks | dicari `ilike`, tabel kecil | — |
| `status` | tidak diindeks sendiri | selalu bersama `contract_template_id`/`current_version_id` | — |

### A2.4 Rencana impor dari Node (C5)

Per tenant, dari koneksi `legacy` (tabel `clauses`, `templates`, `variables`, `settings`): kategori dicocokkan ke `contract_categories` lewat `legacy_key`/nama (galat bila tak ada); klausul → `contract_clauses` (`legacy_key` = `cls-…`, `revision` = `version`); isi HTML diubah sesuai keputusan Q-T1, dengan laporan per klausul untuk format yang hilang (tabel, gambar, warna); variabel global Node disalin ke tiap tenant yang diimpor + kunci yang muncul di `requiredVariables`/isi pasal tapi tidak ada di tabel variabel (peringatan); template → satu `contract_templates` + satu versi `published` dengan `version_no = version ?? 1` (catatan: teks pasal diambil dari isi klausul **saat impor**, karena Node tak menyimpan teks pasal per versi template); `clauseIds` yang hilang = peringatan. `--dry-run`, semua-atau-tidak per tenant, idempoten, `--rollback`, fixture sintetis, tolak tanpa dry-run bersih (aturan B3).

### A2.5 Keamanan template

- **Sintaks variabel:** hanya `{{Kunci}}` dengan kunci `^[A-Za-z][A-Za-z0-9_]{0,59}$`. Tanpa ekspresi, filter, fungsi, atau logika (bukan Blade/Twig/Handlebars); substitusi = pencarian string murni (`preg_replace_callback` dengan peta nilai). Kunci tak dikenal di isi pasal ditolak saat simpan dengan pesan "Kolom isian {{X}} belum ada. Tambahkan di daftar kolom isian atau perbaiki ejaannya."
- **Nilai yang disubstitusi** selalu di-escape untuk keluaran tujuan (HTML: `e()`; PDF: teks). Nilai tidak pernah ditafsirkan ulang sebagai template (tidak ada substitusi berantai).
- **Konten pasal:** usul teks terstruktur (Q-T1), tidak menyimpan HTML → tidak ada XSS tersimpan. Bila HTML dipilih, wajib sanitasi di server (allowlist tag tanpa `img`, `style`, `font`, `a href`), butuh dependensi baru.
- **AI:** yang dikirim hanya deskripsi yang diketik pengguna + daftar kunci variabel (tanpa nilai), tanpa nama PT, vendor, karyawan, atau isi kontrak; hasil hanya mengisi form (draf), tidak tersimpan otomatis; keluaran divalidasi skema `StructuredGenerator` dan melewati validasi variabel yang sama.
- Batas ukuran: judul 200, isi pasal ≤ 20.000 karakter, ≤ 100 pasal per versi, ≤ 50 variabel per versi.

### A2.6 Kontrak antarmuka untuk Dokumen Eksternal & Karyawan (tanpa membangun modulnya)

- `TemplateCatalog::options(Tenant, DocumentModule, ?categoryId)` → template aktif yang punya versi terbit: `{template_id, version_id, name, category}`.
- `TemplateCatalog::snapshot(versionId)` → `TemplateSnapshot` (immutable): nama, `version_no`, pihak, paragraf pembuka/penutup/recital, pasal `{position, title, body, is_mandatory, is_protected}`, variabel `{code, label, type, required, default, source_field}`.
- `VariableRenderer::render(string $text, array $values, Output $out)` → teks dengan nilai ter-escape; nilai kosong → `[Label]`.
- Kontrak kelak menyimpan `contract_template_version_id` + **menyalin** pasal saat dibuat (paritas Node). Karena versi terbit beku, rujukan ke versi sudah cukup sebagai bukti redaksi.
- Vendor: `VendorDirectory::options(Tenant)` (`id, name`) dan `find()` (profil lengkap, pemanggil wajib cek `vendors.manage` atau izin kontrak yang diputuskan kelak); kontrak menyimpan salinan (`vendorSnapshot`).
- Tidak ada otorisasi di Catalog/Directory (pola `EmployeeDirectory`): pemanggil yang mengecek.

---

## A3. UX untuk pengguna awam

| Istilah Node | Padanan sederhana (usul) |
|---|---|
| Klausul / Clause Library | Pasal / Perpustakaan pasal |
| Template / Master Template | Template kontrak |
| Variabel / `{{CompanyName}}` | Kolom isian (ditampilkan sebagai chip "Nama Perusahaan", bukan kode) |
| Mandatory | Wajib ada (tidak bisa dihapus dari kontrak) |
| Protected / Proteksi Legal | Terkunci (isinya hanya boleh diubah tim Legal) |
| Versi / redaksi | Versi; "Terbitkan" = siap dipakai di kontrak baru |
| Draft | Draf (belum dipakai) |
| Recital Addendum | Kalimat pengantar adendum |
| Paragraf Pembuka/Penutup Kustom | Kalimat pembuka / penutup |
| PIC | Kontak penanggung jawab |
| PPh Dipotong (%) | Potongan PPh (%) |
| Nonaktif | Sembunyikan / Tampilkan (sama dengan Daftar Pilihan) |

Alur paling mudah:
- **Vendor:** daftar (cari nama/NPWP, filter aktif, urut) → "Tambah vendor" membuka Sheet dua bagian ("Data utama", "Rekening & pajak" — bagian kedua hanya untuk pengelola) → simpan. Sembunyikan meminta konfirmasi berisi jumlah kontrak yang memakai.
- **Template:** daftar template (status Draf/Terbit/Disembunyikan) → "Buat template" (halaman, bukan modal) dengan langkah: 1) Nama & kategori, 2) Pasal (pilih dari perpustakaan atau tulis baru; seret/naik-turun), 3) Pihak, 4) Kolom isian (dideteksi otomatis dari pasal; pengguna hanya melengkapi label & wajib/tidak), 5) Pratinjau dengan contoh data → "Terbitkan" (konfirmasi: "Kontrak baru akan memakai versi ini. Kontrak lama tetap memakai versi sebelumnya."). Mengubah template terbit = "Buat versi baru" (draf), versi lama tetap utuh.
- **Pasal:** perpustakaan dengan cari + filter kategori; tombol "Sisipkan ke template" dari halaman template.

---

## Beda dari Node / dokumen (usul yang sudah terlihat)

1. Tidak ada hapus permanen (vendor, pasal, template) → sembunyikan + jumlah pemakaian.
2. Template berversi beku (draf/terbit/pensiun), menggantikan "ubah di tempat + nomor versi"; pasal disalin ke versi template (Node merujuk id hidup).
3. Variabel per PT dan bertipe; kunci divalidasi (Node: kunci bebas, tabel global).
4. Kolom sensitif vendor hanya untuk `vendors.manage` (Node: semua pengguna login).
5. Semua ubah/sembunyikan diaudit (Node: sebagian).
6. Tag klausul tidak dibawa (Q-T6).

## Konflik dokumen vs kode

| # | Dokumen | Kode | Sikap |
|---|---|---|---|
| K1 | `00-peta-sumber.md`: vendor `server.ts 2442-2488` | Route vendor kini di sekitar `2427-2483` (baris bergeser) | Kode menang; rujukan baris di laporan ini yang dipakai. |
| K2 | `01-audit.md` E12: vendor "tunda ke modul Dokumen Eksternal" | Permintaan sekarang: modul Data Vendor sendiri | Permintaan pengguna menggantikan; dicatat. |
| K3 | `01-audit.md` P6: "detail mitra nanti disatukan dengan Vendor (satu tabel pihak eksternal)" | `partners` tanpa detail (CLAUDE.md) | Butuh keputusan Q-V1. |
| K4 | Komentar Node `db.ts:65` tabel `variables` PK `key` (global) vs `src/types.ts:117` `tenantId?` | Variabel efektif hanya milik `t-01` | Laravel: per PT (P7). |

## Risiko dan yang belum diverifikasi

- Isi produksi tidak diketahui: berapa pasal memakai HTML kaya (tabel/gambar) menentukan biaya Q-T1. Disarankan query hitung di DB Node (lihat Q-T1).
- Tidak diketahui apakah ada vendor bernama sama di produksi (memengaruhi indeks unik Q-V5).
- Kontrak Node merujuk `templateId` dan id klausul; importer kontrak kelak harus memetakan ke `legacy_key`.
- Narasi pembuka per kategori dan pratinjau dokumen bergantung pada mesin render kontrak yang belum ada; pratinjau di C3 hanya teks pasal + variabel.

## Temuan di luar lingkup (tidak dikerjakan)

1. Node menyimpan HTML pasal tanpa sanitasi di server (XSS tersimpan bila klien dilewati) — `server.ts` POST/PUT clauses.
2. Node `PUT /api/modules/vendors/:id` menyebar `req.body` ke data (mass assignment) dan `GET` membuka rekening ke semua pengguna.
3. Prompt AI Node menyisipkan teks pengguna tanpa batas (prompt injection) — `server.ts:7399-7853`.
4. Evaluasi Kinerja Vendor (per kontrak) milik modul kontrak.
5. `default_template_id` di jenis dokumen kontrak (Node `docTypes.defaultTemplateId`) belum ada di `contract_document_types`; masuk modul kontrak atau Fase C (Q-T8).

---

## Pertanyaan terbuka (butuh jawaban sebelum Fase B/C)

**Q-V1. Vendor dan Mitra/Operator: satu tabel atau terpisah?**
Rekomendasi: **terpisah** — tabel `vendors` baru; Mitra tetap daftar nama di Daftar Pilihan, tanpa detail; halaman Data Vendor hanya berisi vendor (tanpa tab operator). Node juga memisahkan keduanya; mitra berjumlah ribuan dan hanya dipakai untuk klasifikasi arsip. [Keyakinan sedang — bila mitra di produksi punya `operatorDetails` terisi, penyatuan jadi lebih menarik.]

**Q-V2. Siapa boleh membuka halaman Data Vendor?**
Rekomendasi: hanya `vendors.manage` (super_admin, admin, legal). Pengguna lain kelak hanya melihat nama vendor di form kontrak lewat `options`. Tanpa izin baru. [Keyakinan sedang — keputusan organisasi.]

**Q-V3. Kolom sensitif yang disimpan.**
Rekomendasi: simpan semua kolom Node (dibutuhkan variabel kontrak `VendorNPWP`, `VendorBank*`, `VendorPPhRate`, PIC untuk kirim WA), terlihat hanya oleh `vendors.manage`; tidak masuk nilai audit, log, atau AI. [Keyakinan sedang — data pribadi/keuangan, keputusan Anda.]

**Q-V4. Enkripsi nomor rekening di database?**
Rekomendasi: ya (cast `encrypted`, kunci `APP_KEY`), disamarkan di daftar. Konsekuensi: tidak bisa dicari, dan rotasi `APP_KEY` perlu re-enkripsi. [Keyakinan sedang.]

**Q-V5. Nama vendor unik per PT?**
Rekomendasi: ya (tanpa beda huruf besar/kecil); importer melaporkan nama ganda sebagai galat yang harus dibereskan dulu. Alternatif: unik pada NPWP. [Keyakinan sedang — data produksi belum dicek.]

**Q-V6. Vendor dan Customer.**
Node memakai satu daftar untuk keduanya tanpa kolom jenis. Rekomendasi: tetap satu daftar tanpa kolom jenis (nama menu "Data Vendor"); jenis pihak tetap dipilih di form kontrak. [Keyakinan rendah — aturan bisnis.]

**Q-V7. Impor/ekspor CSV dari layar?**
Node tidak punya. Rekomendasi: tidak di fase ini; hanya importer artisan dari Node. [Keyakinan tinggi.]

**Q-T1. Format isi pasal.** Node: HTML kaya (tebal, miring, warna, ukuran, tabel, gambar, tautan). Laravel belum punya editor kaya.
Pilihan: (a) teks terstruktur tanpa HTML: paragraf, daftar bernomor/berbutir, dan **tebal** saja, dengan editor `Textarea` + toolbar kecil buatan sendiri; impor mengubah HTML ke format ini dan melaporkan apa yang hilang (tabel, warna, gambar); (b) editor kaya (mis. Tiptap, dependensi baru) + sanitizer HTML di server (dependensi composer baru).
Rekomendasi: **(a)** — aman dari injeksi tanpa dependensi, cocok dengan PDF engine teks; naik ke (b) hanya bila produksi banyak memakai tabel di pasal. Untuk memutuskan, Anda bisa menjalankan di DB Node: `select count(*) filter (where data->>'content' ~* '<table|<img') as kaya, count(*) from clauses;`. [Keyakinan sedang.]

**Q-T2. Model versi.**
Rekomendasi: versi template beku (draf → terbit → pensiun) dengan **salinan** pasal di tiap versi; perpustakaan pasal tetap bisa diubah dan draf diberi tanda "pasal sumber diperbarui". [Keyakinan tinggi — memenuhi "template yang dipakai dokumen tidak berubah diam-diam" dan sesuai cara kontrak Node menyalin pasal.]

**Q-T3. Siapa boleh membaca perpustakaan & template?** Node: admin, legal, super_admin, **dan hr_ga** (baca saja).
Rekomendasi: halaman kelola hanya `templates.manage`; HR/GA mendapat template lewat form Dokumen Karyawan kelak (opsi), bukan halaman library. Bila HR/GA perlu melihat library sekarang, itu izin baru → keputusan Anda. [Keyakinan rendah.]

**Q-T4. Kolom isian per PT dan bisa ditambah pengguna?** Node membolehkan mengetik kunci baru bebas di template.
Rekomendasi: daftar kolom isian per PT (disalin dari 11 bawaan + kunci vendor), dikelola di halaman Klausul & Template oleh `templates.manage`; template hanya memilih dari daftar (sesuai aturan "tanpa Lainnya"). [Keyakinan sedang.]

**Q-T5. Narasi pembuka per kategori (`categoryOpeningParagraphs`) ikut dimigrasi sekarang?**
Rekomendasi: belum; template tetap punya kalimat pembuka/penutup/recital sendiri. Narasi per kategori dipindah bersama mesin render kontrak. [Keyakinan sedang.]

**Q-T6. Tag pasal Node dibuang?**
Rekomendasi: ya; pencarian memakai judul + isi + kategori. Bila tag dipertahankan, butuh tabel tag bertipe. [Keyakinan sedang.]

**Q-T7. Saran AI.** Di Node library hanya punya "Tulis klausul dengan AI"; "Saran AI" ada di wizard kontrak.
Rekomendasi: C4 hanya "Tulis draf pasal dengan AI" untuk `templates.manage`, lewat `app/Support/Ai`, prompt hanya deskripsi pengguna (tanpa data PT/vendor/karyawan), hasil hanya mengisi form. "Saran AI" ditunda ke modul kontrak. [Keyakinan sedang — perlu persetujuan Anda.]

**Q-T8. Template bawaan per jenis dokumen kontrak (`defaultTemplateId`).**
Rekomendasi: tunda ke modul kontrak (perlu kolom baru di `contract_document_types`). [Keyakinan sedang.]

**Q-T9. Seeder bawaan.**
Rekomendasi: 8 pasal, 11 kolom isian, 3 template dari kode Node, lewat config + `master-data:seed-defaults`-style command, berlabel "belum diverifikasi dengan data produksi"; template bawaan diseed sebagai **draf**, bukan terbit, supaya admin meninjaunya dulu. [Keyakinan sedang.]
