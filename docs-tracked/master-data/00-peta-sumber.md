# 00 — Peta Sumber untuk Migrasi Master Data

Dibuat 2026-11-20. Hanya peta; tidak ada kode yang diubah. Urutan kebenaran: kode Laravel + `CLAUDE.md` → kode Node (`../management-contract/`) → dokumen (petunjuk saja).

Catatan penting: folder `docs/` di-*ignore* git (`.gitignore:46` → `/docs`). Dokumen ini dan dokumen lain di `docs/` **tidak ikut commit**. Tanggal di bawah adalah tanggal ubah berkas (`stat`), bukan riwayat git.

Folder Node **bisa diakses** (`/Users/macpro/Documents/maganghub/management-contract`). `docs/migrasi-karyawan/` **tidak ada** (baik di `smart-clm/docs` maupun di Node).

## 1. Indeks `smart-clm/docs/`

Status: **aktual** = cocok dengan kode saat dicek cepat; **usang** = ada klaim yang bertentangan dengan kode/CLAUDE.md; **AS-IS Node** = menggambarkan Node, masih berguna sebagai spesifikasi perilaku tetapi bukan Laravel.

| Berkas | Tgl ubah | Topik | Status | Bukti cepat |
|---|---|---|---|---|
| `00-ringkasan.md` | 2026-11-18 | Ringkasan analisis DCS + keputusan | usang | keputusan K-x sudah diambil di CLAUDE.md (bagian DCS) |
| `01-inventaris-endpoint.md` | 2026-11-18 | Endpoint `/api/dcs/*` Node | AS-IS Node | — |
| `02-model-data.md` | 2026-11-18 | Model data DCS + rancangan Laravel | usang (rancangan) | skema nyata di `database/migrations/*dcs_*` |
| `03-aturan-bisnis.md` | 2026-11-18 | Aturan bisnis DCS Node | AS-IS Node, sebagian digantikan | penomoran: Laravel tidak mengisi celah (CLAUDE.md, DCS S2) |
| `03-frontend-auth.md` | 2026-11-18 | Auth di React + peta Inertia | usang | registrasi publik dihapus (CLAUDE.md, Product decisions) |
| `04-inventaris-ui.md` | 2026-11-18 | Inventaris UI React → shadcn-vue | sebagian aktual | komponen nyata: §4 di bawah |
| `04-titik-sentuh-integrasi.md` | 2026-11-18 | DCS vs modul lain | AS-IS Node | `actionPermissions` sudah diganti RBAC DB |
| `05-checklist-uji-manual.md` | 2026-11-18 | Uji manual auth + RBAC | usang sebagian | menyebut alur pendaftaran |
| `05-rencana-migrasi-laravel.md` | 2026-11-18 | Rencana backend DCS | **usang** | baris 25/40: "PDF tetap Node / `NodePdfEngine`"; kode: `PhpPdfEngine` satu-satunya binding (CLAUDE.md) |
| `09-prd.md`, `10-spec.md`, `11-plan.md` | 2026-11-18 | Migrasi auth/user/RBAC | **usang** | `10-spec.md:74` `users.id` string; kode: `$table->id()` bigint (`0001_01_01_000000_create_users_table.php:15`); `09-prd.md:9` registrasi + verifikasi email aktif, CLAUDE.md: dihapus |
| `12-checklist-staging-dcs.md` | 2026-11-18 | Checklist staging DCS | usang sebagian | baris 17/29 `INTERNAL_PDF_SECRET`, `NodePdfEngine` |
| `_progress.md` | 2026-11-18 | Progres analisis DCS | usang | — |
| `deviasi.md` | 2026-11-18 | Deviasi DEV-0xx DCS | sebagian usang | DEV-020 "MENUNGGU KEPUTUSAN" padahal mesin PHP sudah ada |
| `auth/00..03-*-as-is.md` | 2026-11-18 | Auth, RBAC, user/tenant Node | AS-IS Node (berguna) | `auth/02-user-tenant-as-is.md` cocok dengan `db.ts:99-124` |
| `auth/06-koeksistensi-dan-data.md` | 2026-11-18 | Koeksistensi Laravel/Node | usang sebagian | — |
| `auth/07-memo-keputusan.md`, `auth/deviasi-auth.md` | 2026-11-18 | Keputusan & deviasi auth | sebagian digantikan CLAUDE.md | — |
| `auth/openapi-auth.yaml` = `openapi-auth.yaml` | 2026-11-18 | OpenAPI auth Node | AS-IS Node | `cmp` sama persis |
| `data/role_permissions.csv` | 2026-11-18 | Matriks izin lama | aktual | `cmp` sama dengan `tests/Fixtures/role_permissions.csv` |
| `data/roles.csv`, `permissions.csv`, `endpoint_enforcement.csv`, `endpoints_all.csv` | 2026-11-18 | Katalog RBAC/endpoint Node | AS-IS Node | `docs/endpoints_all.csv` ≠ `docs/data/endpoints_all.csv` (beda di baris 1) — belum diperiksa mana yang benar |
| `schema/module-map.md` | 2026-11-19 | Peta modul Node + urutan kerja | aktual sebagai peta; daftar "utang analisis" masih terbuka | dipakai sebagai titik awal dokumen ini |

Yang relevan untuk master data: `schema/module-map.md`, `auth/02-user-tenant-as-is.md`, `04-titik-sentuh-integrasi.md` (departemen/arsip). Sisanya DCS/auth.

## 2. Peta folder Node `management-contract/`

Penyimpanan: Postgres, satu tabel JSONB per koleksi (`db.ts:60-124`), dimuat ke memori; DCS memakai tabel relasional sendiri (`dcs/schema.ts`). Pengaturan per tenant: tabel `settings("tenantId", data jsonb)` (`db.ts:124`); kategori: tabel `categories(tenantId, name, sort_order)` (`db.ts:120-123`).

| Area | Berkas utama | Lokasi kunci |
|---|---|---|
| Konfigurasi & master data (server) | `server.ts` | `defaultSettings()` 1810-1940; `withSettingsDefaults()` 2041-2104; `GET/PUT /api/settings` 2119-2200; kategori `/api/clause-categories` 1979-2026; `catsFor` default 241-246 |
| Konfigurasi (UI) | `src/App.tsx` | grup menu `getCfg()` 5717-5752; turunan `masterData` 6420-6445; tab Konfigurasi 16511-18900 (`configTab`) |
| Perusahaan (tenant), platform, kode | `server.ts` | `/api/tenants` 1227-1297; `/api/number-companies` 1237-1247; `platformsForTenant` 1597; `companyCodeOf` 1626; seed PT/platform 7692-7695 |
| Klasifikasi form (PT → …) | `src/App.tsx` | eksternal `TaxonomyFields` 1207-1350; karyawan `InternalTaxonomyFields` 1355-1435 |
| Mitra/Operator | `operatorDefaults.ts` | `DEFAULT_OPERATOR_GROUPS`, `sanitizeOperatorGroups` |
| Jenis dokumen kontrak + seed | `server.ts` | `masterData.docTypes` 1880-1895; seed eksternal 7684-7691, karyawan 7697-7710 |
| Template & klausul | `server.ts`, `clause-html.ts`, `template-docx.ts` | `/api/clauses` 1973-2322, `/api/templates` 2324-2370; variabel bawaan `defaultVariables` 1498-1510 (tanpa route CRUD) |
| Kontrak / dokumen eksternal | `server.ts`, `contract-documents.ts`, `review-*.ts`, `src/UploadedDocumentWorkspace.tsx` | tipe `Contract` `src/types.ts:337-640` |
| Karyawan | `server.ts` 2403-2440; `termination-letter.ts` | tipe `EmployeeData` `src/types.ts:760-768` |
| Vendor | `server.ts` 2442-2488 | tipe `VendorData` `src/types.ts:770-790` |
| Penomoran kontrak | `numbering-utils.ts`, `server.ts` 2620-2780 | counter `number_counters`, kunci `tid|jenis|tahun|p:|c:` (`server.ts:2655-2659`) |
| Penomoran DCS | `dcs/numbering.ts` | sudah dimigrasi (jangan diubah) |
| Hak akses | `src/roles.ts` | `PERMISSIONS` 130-170 |
| Arsip | `server.ts` 2489-2560 | `SubFolder` `src/types.ts:965-972` |

## 3. Konvensi Laravel yang relevan (ringkasan dari `CLAUDE.md`)

- **Lapisan**: route bernama + middleware `can:` → controller tipis → FormRequest (`authorize()` ke Policy, `Gate::inspect` bila perlu 404; metode bertipe seperti `userData()`) → Action `app/Actions/<Domain>/<Verb><Noun>` (`handle()`) → Resource (`can` berisi hasil policy) → `Inertia::render` / redirect + `Inertia::flash('toast')`. Tidak ada Service layer.
- **Daftar**: tidak ada "Query object"; polanya `ListSpec` (`app/Support/Listing/*ListSpec.php`: search, `Filter`, `Sort`, `parse()/paginate()/meta()`), contoh `DepartmentListSpec.php`.
- **Skema**: id bigint, `timestamptz`, `numeric(18,2)`, `onDelete` eksplisit, `created_by_id`/`updated_by_id`, status varchar + enum PHP. Tabel milik tenant: `tenant_id` NOT NULL. Lookup: `tenant_id` null = default bawaan, `code`, `name`, `is_active`, `is_system`; nonaktifkan, jangan hapus. Tanpa EAV/form builder.
- **RBAC**: izin di `App\Enums\Permission` (sudah ada `SettingsManage`, `SettingsEmploymentTypes`, `TemplatesManage`, `EmployeesManage`, `VendorsManage`, `BudgetsManage`); cek lewat `Gate`/`can:`; lintas tenant → 404 (`Response::denyAsNotFound()`); `role_id`/`tenant_id`/`is_active` diisi `forceFill` di Action.
- **Audit**: `App\Actions\Audit\RecordAuditLog::handle($actor, $event, $subject, $before, $after, $tenantId)` ke `audit_logs` (append-only), dipanggil di transaksi yang sama.
- **Tenant**: scoping eksplisit lewat relasi `Tenant` (bukan global scope); super admin tidak punya perusahaan dan ditolak di layar perusahaan (pola `DocumentPolicy::configure`).
- **Menu**: `App\Support\Navigation\NavigationMenu` — modul "Master Data" sudah berisi placeholder `klausul-template`, `kategori-kontrak`, `vendor`, `karyawan`, `mitra-operator`; "Pengaturan Sistem" berisi `perusahaan`.
- **Frontend**: ≤200 baris/berkas (`tests/Feature/Frontend/FileSizeTest.php`), list kit wajib, token warna saja (`ThemeTest`), Indonesia, mobile 375px.
- **Teks/label**: belum ada folder `lang/`; label filter/sort ditulis di `ListSpec` (PHP), label izin di `Permission::label()`.

## 4. Halaman Inertia dan komponen yang sudah ada

**Halaman** (`resources/js/pages`): `Dashboard`; `admin/users/{Index,Create,Edit}`; `admin/roles/{Index,Create,Edit}`; `ai-tools/comparisons/{Index,Create,Show}`; `auth/*`; `documents/{Index,Create,Edit,Show,Queue,Timeline}`; `documents/config/types/{Index,Create,Edit}`, `clauses/{Index,Create,Edit}`, `departments/Index`, `numbering/Index`; `modules/InDevelopment`; `settings/{Profile,Security,Appearance}`.

**Pola halaman master data terdekat**: `documents/config/departments/Index.vue` (list kit + `DepartmentDialog` lewat `ActionDialog`) dan `documents/config/types/*` (form penuh + `FormActions`).

**shadcn-vue (`components/ui`)**: alert, alert-dialog, avatar, badge, breadcrumb, button, card, checkbox, collapsible, dialog, dropdown-menu, input, input-otp, label, navigation-menu, popover, select, separator, sheet, sidebar, skeleton, sonner, spinner, table, textarea, tooltip. *Tidak ada*: tabs, switch, radio-group, command/combobox (ada `SearchSelect.vue`).

**Komponen aplikasi yang bisa dipakai ulang**: `list/*` (`ListPage`, `DataTable` → kartu di bawah md, `ListToolbar`, `FilterSheet`, `ActiveFilterChips`, `ListPagination`, `ListEmpty`), `ActiveStatusBadge`, `ChoiceButtons`, `ConfirmHost` + `useConfirm`, `PageTabs`, `SearchSelect`, `Heading`, `InputError`, `documents/actions/ActionDialog`, `documents/config/{ConfigHeader,ConfigTabs,FormActions,StringListEditor,ToggleActive}`. Composable: `useListQuery`, `usePermissions`, `useUnsavedGuard`, `useConfirm`.
