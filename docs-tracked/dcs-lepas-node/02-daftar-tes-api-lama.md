# Lepas dari Node (DCS) — daftar tes yang menyentuh API lama

Tanggal: 2026-11-23. Hanya daftar; **tidak ada tes yang dihapus atau diubah**. Fase E menunggu konfirmasi P1 dan log proxy.

## Cara membaca

Klasifikasi dibuat dari nama tes, pembacaan helper tiap berkas, dan perbandingan dengan tes web yang ada (`tests/Feature/Http/Controllers/Documents/*`, `tests/Feature/Actions/Dcs/*`, `tests/Feature/Support/Dcs/*`). Ini penilaian saya dari nama dan struktur, **belum** pembacaan baris demi baris tiap tes; periksa kolom C sebelum menghapus apa pun.

- **A. Khusus API lama.** Yang diuji adalah bentuk JSON Node, status 401 tanpa cookie, pesan Node, atau cookie `clm_token`. Hilang bersama API-nya; tidak ada perilaku yang tertinggal tanpa penjaga.
- **B. Ada padanan di tes lain.** Perilakunya (atau layar Vue-nya) sudah dijaga tes web atau tes Action; tes API-nya duplikat. Padanan disebut.
- **C. Hanya terjaga lewat API.** Menguji perilaku Action atau aturan bisnis nyata, tetapi lewat rute `/api/dcs/*` dan tanpa padanan yang saya temukan. **Pindahkan dulu** ke tes Action atau tes web sebelum berkas lamanya dihapus, supaya cakupan tidak turun.

## Ringkasan

| Kelompok | Berkas | Tes | A | B | C |
|---|---|---|---|---|---|
| Jembatan Node (autentikasi, galat) | 3 | 17 | 17 | 0 | 0 |
| Controller API lama (`Http/Controllers/Dcs`) | 11 | 174 | 29 | 90 | 55 |
| Tes lain yang hanya memakai API lama untuk persiapan | 4 | ±5 titik | — | — | diubah, bukan dihapus |
| Helper dan konfigurasi tes | `tests/Pest.php`, `tests/golden/capture.sh` | — | — | — | lihat bawah |

Artinya: sekitar 46 tes aman dihapus langsung (A), sekitar 90 duplikat (B), dan **55 tes (C) perlu dipindahkan dulu**.

## 1. Jembatan Node — seluruhnya A (17 tes)

Hilang bersama `dcs.auth`, `NodeSessionToken`, `DcsErrorRenderer`; tidak ada perilaku lain di dalamnya.

| Berkas | Tes | Isi |
|---|---|---|
| `tests/Feature/Http/Middleware/AuthenticateNodeSessionTest.php` | 8 | cookie `clm_token` → pengguna Laravel; 401 tanpa/rusak/tenant beda; sesi web saja ditolak |
| `tests/Unit/Support/Dcs/NodeSessionTokenTest.php` | 4 | verifikasi JWT HS256 |
| `tests/Feature/Support/Dcs/DcsErrorRendererTest.php` | 5 | bentuk galat `{error, errors}` untuk `/api/dcs*` |

## 2. Controller API lama — `tests/Feature/Http/Controllers/Dcs/*` (174 tes)

Semua berkas di sini memanggil `/api/dcs/*` (langsung atau lewat helper `dcsAs()`, `call()`, `api()`, `createDocument()`).

| Berkas | Tes | A | B | C |
|---|---|---|---|---|
| `AiAndAppendixTest` | 10 | 1 | 4 | 5 |
| `ClauseControllerTest` | 13 | 4 | 7 | 2 |
| `DepartmentControllerTest` | 7 | 0 | 2 | 5 |
| `DocTypeControllerTest` | 23 | 8 | 11 | 4 |
| `DocumentControllerTest` | 35 | 6 | 16 | 13 |
| `DocumentVersionControllerTest` | 8 | 2 | 3 | 3 |
| `ExternalReviewTest` | 11 | 0 | 7 | 4 |
| `NumberingRuleControllerTest` | 12 | 3 | 7 | 2 |
| `VersionAuthoringTest` | 16 | 3 | 11 | 2 |
| `VersionReleaseTest` | 17 | 0 | 9 | 8 |
| `VersionReviewTest` | 22 | 2 | 13 | 7 |
| **Jumlah** | **174** | **29** | **90** | **55** |

### A — khusus API (29)
Contoh: "answers 401 without a session" (AiAndAppendix, Clause, DocType, DocumentController), "returns each type in the Node shape", "wraps legacy flat signature columns", "requires a tenant choice from a super admin" (`?tenantId`), "answers 404 for an id that is not a uuid", "accepts multipart JSON parts … Node messages", "answers JSON null before any content exists", "is reflected in hasFile", "starts at the given Terbitan and Revisi" (web mengabaikannya), "sets the owner by legacy id", "lists … in the Node shape", "adds a revision … raw column names", "lists signatures … in the Node shape", "only accepts under_review and draft as targets", "changes only the supplied fields" / "does not clear a description" / "keeps attached types when none are supplied" (semantik PATCH parsial API), "returns the tenant default rule in the Node shape", "requires a docTypeId".

### B — ada padanan (90)
Ringkasnya, perilaku ini sudah dijaga oleh:
- `DocumentConfigTest` (jenis, klausul, departemen, penomoran: buat/ubah/nonaktifkan/pulihkan, 404 PT lain, izin).
- `DocumentActionsTest` dan `PersonalSignersTest` (ajukan, tanda tangan, minta revisi, tarik, komentar, rilis/jadwal/batal/tarik, distribusi, penolakan lewat toast).
- `DocumentWizardTest` (buat dokumen, simpan draf per bagian, revisi usang, susun PDF dan galat mesin, unggah PDF, lampiran, ekstrak metadata).
- `DocumentShowTest` (halaman dokumen, riwayat, PDF ber-watermark dan tanda terima baca).
- `DocumentRevisionTest`, `VersionReviewExtrasTest`, `ExternalReviewPageTest` (Fase B–D), `SeedDcsTenantDefaultsTest`, `NumberFormatTest`, `NumberAllocatorTest`.

### C — pindahkan dulu (55)

**`DocumentControllerTest` (13)** → tes Action `CreateDocument` / `ReleaseDocumentNumber`
counts per type/dept/year separately · keeps counters apart between tenants · reads the year in the numbering time zone · takes a typed number for an uploaded PDF without using the counter · cuts a typed number at 80 characters · answers 409 for a typed number already taken · returns the number when the document cannot be created · records the history of the new document · creates an unknown department on the fly · rejects a department that is invalid or switched off · refuses an owner of another tenant · does not look at the pool of released numbers · never moves the counter back · cannot release the same number twice.

**`VersionReleaseTest` (8)** → tes Action `ReleaseVersion`, `ActivateDueReleases`, `SendReviewReminders`, `ViewVersion`, `DistributeVersion`
brings an approved version into force and supersedes the old one at the same instant · uses an explicit effective date · releases due versions at their scheduled moment (`dcs:release-due`) · sends each review reminder once a day, inside the window only · marks an effective version past its review date as expired · serves the English version only when a translation exists · refuses a missing file, a tampered file and an engine failure without a receipt · never resets someone who already read it.

**`VersionReviewTest` (7)** → tes Action `SignVersion`, `SubmitVersionForReview`
refuses to resubmit while the file still carries old signatures · stamps the box of the signature sheet for a composed file and a generic block for an uploaded one · signs a plain uploaded file with one generic column · lets a super admin sign any column · refuses a file whose bytes no longer match the hash · lets the author resubmit after recomposing · keeps a document that was ever signed from releasing its number.

**`AiAndAppendixTest` (5)** → tes Action `TranslateVersion`, `ExtractDocumentMetadata`, `StoreAppendixFile`
translates the content and hands the engine the English copy · rejects a translation that lost sections (502) · drops a document type the tenant does not have · needs a PDF or image · needs a PDF or Word file within the size limit.

**`DepartmentControllerTest` (5)** → tes Action `CreateDepartment` / `UpdateDepartment` (atau `DocumentConfigTest`)
refuses the same code in any case · allows the same code in another tenant · rejects codes that would break a document number · renames or deactivates but never changes its code · answers 404 for a department of another tenant.

**`DocTypeControllerTest` (4)** → `DocumentConfigTest` atau tes Action `CreateDocType` / `UpdateDocType`
rejects a duplicate code (409), even of a deactivated type · allows the same code in another tenant · rejects clauses of another tenant · restores the default signers when given an empty list of groups.

**`ExternalReviewTest` (4)** → `ExternalReviewPageTest` / tes Action
replaces an open link, so the old token stops working · has no expiry without a positive number of days · unlocks only a version that has a live link · records a guest comment with a clean anchor and tells the owner.

**`DocumentVersionControllerTest` (3)** → tes Action `CreateVersion`
bases the next number on the newest version, not the effective one · answers 409 when the Terbitan and Revisi are already used · records the history of the new version.

**`ClauseControllerTest` (2)** → `DocumentConfigTest` / tes Action
attaches document types by code · rejects unknown document type codes instead of widening the clause to all types.

**`NumberingRuleControllerTest` (2)** → `DocumentConfigTest` / tes Action `SaveNumberingRule`
creates an override for a type with the default scope tokens, then updates it in place · is a no-op without an override and never removes the default rule.

**`VersionAuthoringTest` (2)** → `DocumentWizardTest` / tes Action `SaveDraftContent`, `UploadVersionFile`
links clauses of the tenant and refuses unknown ones · requires a PDF within the size limit.

## 3. Tes lain yang memakai API lama hanya untuk persiapan (diubah, tidak dihapus)

| Berkas | Baris | Pemakaian | Ganti dengan |
|---|---|---|---|
| `Documents/PersonalSignersTest.php` | 70 | helper menandatangani lewat `POST /api/dcs/versions/{id}/sign-approve` | rute web `documents.versions.sign` atau Action `SignVersion` |
| `Documents/TaskQueueTest.php` | 105 | satu tes menandatangani lewat API | sama |
| `Actions/Dcs/ExportLegacyDcsTest.php` | 134 | membuat dokumen "setelah impor" lewat `POST /api/dcs/documents` | Action `CreateDocument` atau `documents.store` |
| `Actions/Dcs/ImportLegacyDcsTest.php` | 85, 86, 121, 140 | membuat dokumen, melihat PDF, dan membuka tautan review impor lewat API | Action/web `documents.versions.file`, dan `/tinjau/dcs/{token}` untuk tautan review |

## 4. Helper, konfigurasi, dan non-tes

- `tests/Pest.php`: `nodeJwt()` dan `dcsAs()` ikut dihapus setelah tidak ada pemakai; `config(['dcs.node_jwt_secret' => …])` hilang bersamanya.
- `tests/golden/capture.sh`: merekam API **Node** di staging, bukan Laravel; tetap (P8).
- `tests/Feature/Actions/Dcs/ConcurrentDocumentCreationTest.php` dan `PhpPdfEngineEndToEndTest.php` tidak memakai API lama; tidak terdampak.

## 5. Hal di luar tes yang ikut menjadi mati setelah API dihapus

Tanyakan sebelum dibersihkan (bukan bagian rencana awal Fase E):
- `CreateVersion::handle(..., $overrideMajor, $overrideMinor)` dan `StoreVersionRequest::overrideMajor/Minor`: hanya dipakai API; web tidak menawarkan nomor manual.
- Masukan `initialMajor/initialMinor` dan `ownerUserId` berbentuk id Node di `CreateDocument`/`StoreDocumentRequest`: web mengabaikannya atau memakai id Laravel.
- Resource khusus API (`app/Http/Resources/Dcs/*`) kecuali `DocTypeResource` dan `ClauseResource`, yang masih dipakai `DocTypeConfigController` dan `ClauseConfigController`.
- `ExtractMetadataController` dan rute `api/ai/extract-dcs-metadata`.

## Urutan yang saya usulkan (menunggu persetujuan Anda dan konfirmasi P1)

1. Pindahkan 55 tes C ke tes Action/web, dijalankan hijau berdampingan dengan tes lama.
2. Ubah 4 berkas di bagian 3 supaya tidak lagi memakai `dcsAs()`.
3. Setelah P1 dan log proxy terkonfirmasi: hapus rute, controller, Resource khusus API, middleware, config, lalu tes A dan B (dengan daftar final yang Anda setujui per berkas).
4. Perbarui `CLAUDE.md` dan `docs/12-checklist-staging-dcs.md`.

## Hasil langkah 1 dan 2 (2026-11-23)

Isi tiap tes kelompok C dibaca, lalu dipindahkan ke 12 berkas baru (65 tes). **Tidak ada tes lama yang dihapus.**

Temuan saat membaca isi (selain yang tertulis di atas):
- **Ternyata B:** ClauseControllerTest "attaches document types by code" sudah dijaga `DocumentConfigTest` ("creates, edits, switches off and restores a clause" memeriksa `applicableDocTypeCodes`). Tidak dipindahkan lagi.
- **Ternyata A:** DocumentControllerTest "drops an empty department from the number" (tertulis B). Pratinjau web `documents.number-preview` menjawab `number: null` bila departemen kosong, jadi perilaku itu hanya ada di API lama. Tidak dipindahkan.
- **Daftar C DocumentControllerTest** memuat 14 nama walau judulnya tertulis 13; keempat belas dipindahkan (dua di antaranya digabung dalam satu tes).
- **B yang kurang kuat:** "refuses once a version is past draft, naming the statuses" dan "refuses a number that did not come from a counter" hanya dijaga web pada tombolnya (`can.release_number`), bukan pada pesan Action. Pesan itu kini tidak ada penjaganya di luar tes API lama; saya tidak memindahkannya karena tertulis B, tetapi sebaiknya ditambah sebelum Fase E.

Berkas baru: `Actions/Dcs/{CreateDocumentRules,CreateVersionRules,DraftClauseRules,ReleaseRules,SigningRules,TranslationRules}Test` dan `Http/Controllers/Documents/{ConfigRules,DepartmentRules,DocTypeRules,DocumentUploadValidation,DraftUploadLimits,ExternalReviewRules}Test`.

## Hasil Tahap 1 (2026-11-23)

- Dua pesan Action tanpa penjaga kini punya tes: `CreateDocumentRulesTest` ("refuses once a version is past draft, naming the statuses", "refuses a number that did not come from a counter").
- Mutation check pada enam Action (rilis, penjadwal, tanda tangan, nomor): semua tes baru gagal bila Action dirusak; berkas dikembalikan, checksum sama.
- Pemeriksaan acak 15 tes kelompok B: 4 ternyata punya sisi kelompok C (pesan penolakan penandatangan yang bukan peran, pesan tarik versi yang belum berlaku, notifikasi dan riwayat saat diajukan, pesan format nomor kosong/tanpa urutan). Keempatnya dipindahkan (`SigningRulesTest`, `ReleaseRulesTest`, `ConfigRulesTest`). **Perkiraan: sekitar 20-25% kelompok B (±18-22 dari 90) masih punya sisi C; periksa seluruh kelompok B sebelum menghapus.**
- `composer test:pg` kini memakai `@php -d memory_limit=1G vendor/bin/pest ...` (batas 128 MB dari php.ini mesin ini menyebabkan kehabisan memori).
- Token tautan review: kode Node membuatnya dengan `randomBytes(24).toString("hex")` (`server.ts:9453`), jadi heksadesimal 48 karakter; satu-satunya pembuat token DCS. Rute tidak diubah. Konfirmasi bahwa token yang sudah beredar semuanya berformat itu masih menunggu jawaban Anda.
- Konfirmasi P1, log proxy, dan format token belum diisi, jadi Tahap 2 tidak dikerjakan.
