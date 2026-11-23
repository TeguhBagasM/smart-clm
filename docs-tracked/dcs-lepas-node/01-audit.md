# Lepas dari Node (DCS) — Fase A: audit baca-saja

Tanggal: 2026-11-23. Tidak ada kode yang diubah. Aturan kerja: BLOK 0 (aturan kerja tetap, `CLAUDE.md`, `AGENTS.md`); aturan 7 dikecualikan untuk tugas ini. `PhpPdfEngine`, `NumberAllocator`/`NumberingMask`, dan `LlmDcsAi` tidak disentuh.

## 1. Tabel paritas

`routes/api.php` memuat **47 rute `/api/dcs/*`**, **3 rute publik `/api/dcs-external-review/{token}`**, dan 1 rute `api/ai/extract-dcs-metadata` (`ExtractMetadataController`, di luar daftar 50 tetapi memakai `dcs.auth` juga).

| # | Rute lama | Padanan web (`documents.*`) | Action | Status |
|---|---|---|---|---|
| 1-4 | `clauses` GET/POST/PUT/DELETE | `config.clauses.index/store/update/destroy` (+ `restore`) | `CreateClause`, `UpdateClause`, `DeactivateClause` | Ada |
| 5-7 | `departments` GET/POST/PUT | `config.departments.index/store/update` | `CreateDepartment`, `UpdateDepartment` | Ada |
| 8-11 | `doc-types` GET/POST/PUT/DELETE | `config.types.*` (+ `restore`) | `CreateDocType`, `UpdateDocType`, `DeactivateDocType` | Ada |
| 12-14 | `numbering-rule` GET/PUT/DELETE | `config.numbering.index/update/destroy` | `SaveNumberingRule`, `RemoveNumberingOverride` | Ada |
| 15 | `preview-number` | `number-preview`, `config.numbering.preview` | — | Ada |
| 16 | `documents` GET | `documents.index` | — | Ada |
| 17 | `documents` POST | `documents.store` | `CreateDocument` | Ada |
| 18 | `documents/{id}` GET | `documents.show` | — | Ada |
| 19 | `documents/{id}/timeline` | `documents.timeline` | — | Ada |
| 20 | `documents/{id}/release-number` | **tidak ada** | `ReleaseDocumentNumber` | **Celah** |
| 21 | `documents/{id}/versions` POST (revisi baru) | **tidak ada** | `CreateVersion` | **Celah** |
| 22-24 | `.../versions/{vid}/make-effective`, `cancel-release`, `withdraw` | `versions.release`, `cancel-release`, `withdraw` | `ReleaseVersion`, `ScheduleVersionRelease`, `CancelVersionRelease`, `WithdrawVersion` | Ada |
| 25 | `lampiran-upload` | `documents.appendix-upload` | `StoreAppendixFile` | Ada |
| 26-27 | `versions/{id}/draft` GET/PATCH | `versions.edit`, `versions.draft.update` | `SaveDraftContent` | Ada |
| 28-29 | `versions/{id}/compose` GET/POST | `edit` (draf), `versions.render` | `ComposeVersion`, `RenderVersion` | Ada |
| 30 | `versions/{id}/file` POST | `versions.upload` | `UploadVersionFile` | Ada |
| 31 | `versions/{id}/view` | `versions.file` | `ViewVersion` | Ada |
| 32 | `versions/{id}/transition` | `versions.submit`, `versions.return-to-draft` | `SubmitVersionForReview`, `ReturnVersionToDraft` | Ada |
| 33 | `versions/{id}/sign-approve` | `versions.sign` | `SignVersion` | Ada |
| 34 | `versions/{id}/request-changes` | `versions.request-changes` | `RequestVersionChanges` | Ada |
| 35-38 | `approval-roster`, `approvals`, `comments` GET, `receipts` | prop `version` di `documents.show` | — | Ada |
| 39-40 | `comments` POST, `comments/{cid}/resolve` | `versions.comments.store/resolve` | `AddReviewComment`, `ResolveReviewComment` | Ada |
| 41-42 | `distribute`, `acknowledge` | `versions.distribute`, `versions.acknowledge` | `DistributeVersion`, `RecordReadReceipt` | Ada |
| 43 | `review-schedule` PUT | **tidak ada** | `SetReviewSchedule` | **Celah** |
| 44 | `translate` POST | **tidak ada** (hanya tampilan `?lang=en` di pratinjau) | `TranslateVersion` | **Celah** |
| 45-47 | `external-review/enable`, `disable`, `unlock` | **tidak ada** | `EnableExternalReview`, `DisableExternalReview`, `UnlockExternalReview` | **Celah** |
| 48-50 | publik `dcs-external-review/{token}` GET, `/comments`, `/approve` | **tidak ada** | `AddExternalReviewComment`, `ApproveExternalReview` | **Celah** |
| 51 | `ai/extract-dcs-metadata` | `documents.extract-metadata` | `ExtractDocumentMetadata` | Ada (rute lama tinggal sisa) |

Verifikasi dengan grep: sembilan Action berikut **tidak dipanggil dari `app/Http/Controllers/Documents`** (0 pemakai web, 1 pemakai API): `CreateVersion`, `ReleaseDocumentNumber`, `SetReviewSchedule`, `TranslateVersion`, `Enable/Disable/UnlockExternalReview`, `ApproveExternalReview`, `AddExternalReviewComment`. Daftar celah dari tugas terbukti lengkap; **tidak ada celah tambahan**. `ReassignSigner` justru hanya ada di web.

Catatan:
- Halaman web yang memakai Resource lama: `DocTypeConfigController` memakai `Resources\Dcs\DocTypeResource` dan `ClauseConfigController` memakai `ClauseResource`. Dua Resource itu **tidak boleh ikut dihapus** di Fase E; Resource lain (`DocumentResource`, `DraftResource`, ...) perlu dicek ulang saat itu.
- `DcsErrorRenderer` menangani `api/dcs*`, `api/dcs-external-review/*`, dan `api/ai/extract-dcs-metadata`. Tautan publik baru (halaman Inertia) tidak memakainya, jadi renderer ini hanya untuk API lama dan bisa dihapus di Fase E.

## 2. Pemanggil `/api/dcs/*`

**Laravel (Vue/TS):** tidak ada pemanggil. Satu-satunya kemunculan ada di berkas Wayfinder hasil generate (`resources/js/actions/.../Dcs/*`, `resources/js/routes/dcs`), yang tidak diimpor halaman mana pun; berkas itu hilang sendiri saat rute dihapus lalu `wayfinder:generate`. Di PHP, hanya tes, `DcsErrorRenderer`, dan `tests/golden/capture.sh` (merekam API Node di staging, bukan Laravel).

**React (`../management-contract/src/App.tsx`, satu berkas 29.163 baris):** layar DCS yang masih memanggil:

| Layar / fungsi | Baris | Rute yang dipanggil |
|---|---|---|
| Halaman Dokumen DCS (daftar, buat, wizard, tinjau, rilis, konfigurasi jenis/klausul/penomoran) | sekitar 10188-12130 | hampir seluruh `/api/dcs/*` |
| `DcsArchiveBrowser` (arsip/monitoring DCS) | 1445-1495 | `versions/{id}/view` |
| Penampil dokumen di arsip | 19627-19750 | `versions/{id}/view` (+`?download=1`, `?lang=en`) |
| Modal bagikan tautan review | 7893 | `versions/{id}/external-review/enable` |
| `DcsExternalReviewPage` (halaman pihak luar) | 2593-2660 | `/api/dcs-external-review/{token}` + `/comments`, `/approve` |
| Muat data awal | 6231 | `/api/dcs` |
| Dibuka lewat `?dcsReviewToken=` | 13753 | mengarahkan ke `DcsExternalReviewPage` |

**Pertanyaan untuk Anda (P1):** kapan layar React DCS ini dipensiunkan?

## 3. Tautan review eksternal

- Node membuat `url: "{shareBaseUrl}/?dcsReviewToken={token}"` (`server.ts:9455`); Laravel meniru persis (`ExternalReviewController::enable`, basis dari `DCS_SHARE_BASE_URL`, kosong = host permintaan).
- Tautan itu membuka **akar situs (`/`) dengan query `dcsReviewToken`**, lalu SPA React yang menggambar `DcsExternalReviewPage` dan memanggil `/api/dcs-external-review/{token}`. Jadi pihak luar hanya bergantung pada **React** untuk melihat tampilan dan pada **Laravel/Node** untuk datanya.
- Setelah cutover, `/` di Laravel adalah aplikasi Vue; **semua tautan yang sudah terkirim ke pihak luar akan rusak** bila React ikut dimatikan. Ini hambatan utama Fase E.
- Token acak 24 byte (hex 48 karakter), hanya hash SHA-256 yang disimpan (`ExternalReview::hashToken`); token asli ditampilkan sekali saja, sehingga pemilik tidak bisa melihat tautannya lagi setelah modal ditutup.

**Rancangan halaman publik Inertia:**
- Rute `GET /tinjau/dcs/{token}` (halaman), `POST .../komentar`, `POST .../setuju`; di luar grup `auth`, dengan `throttle:60,1` yang sama; token tetap di path. Layout tamu (tanpa sidebar), bahasa Indonesia, responsif 375/768/1280, mode gelap.
- `GET /` dengan `?dcsReviewToken=...` dialihkan 302 ke halaman baru, supaya tautan lama tetap berfungsi. Tautan baru dari Laravel memakai alamat halaman baru.
- Controller tipis + FormRequest (`ExternalCommentRequest`, `ExternalApproveRequest` dipakai ulang) + Action yang sama (`AddExternalReviewComment`, `ApproveExternalReview`). Tautan tidak valid/kedaluwarsa menampilkan halaman galat ramah (404), tanpa membedakan "salah" dan "kedaluwarsa" di luar yang sudah dilakukan sekarang. `Referrer-Policy: no-referrer` dan `noindex` di halaman ini agar token tidak bocor lewat referer atau mesin pencari.
- Data lewat satu Resource khusus tamu (judul, nomor, revisi, tujuan, ruang lingkup, bagian, komentar tanpa id pengguna internal, status kunci), sama dengan JSON sekarang.
- Tidak membuat tabel atau kolom baru.

## 4. Keputusan (BERHENTI di sini, menunggu jawaban)

| # | Keputusan | Rekomendasi |
|---|---|---|
| P1 | Kapan layar React DCS dipensiunkan, dan apakah React tetap melayani `?dcsReviewToken=` sampai halaman publik Laravel ada? | Jawaban tanggal dari Anda. Pensiun React DCS **hanya sesudah** halaman publik Laravel hidup. |
| P2 | Tempat tombol untuk 6 fitur yang belum punya UI | Semua di halaman dokumen lewat `ActionDialog` + `VersionActions`: Revisi baru (header dokumen, saat versi terbaru berlaku/dibatalkan), Lepas nomor (header, dokumen tanpa persetujuan), Jadwal tinjau ulang (panel versi), Terjemahkan (panel pratinjau, memicu `?lang=en`), Tautan review eksternal (panel versi: buat, nonaktifkan, buka kunci). |
| P3 | Izin tiap fitur | Pertahankan seperti API lama: `dcs.edit` untuk revisi, jadwal, terjemah, tautan eksternal; `dcs.release` untuk lepas nomor. Tambah ability pada `DocumentPolicy` (revisi, lepas nomor) dan `DocumentVersionPolicy` (jadwal, terjemah, kelola tautan) yang juga memeriksa status versi, sehingga tombol hanya muncul bila Action akan menerimanya. |
| P4 | Menampilkan tautan eksternal | Tampilkan sekali di dialog (salin ke clipboard) seperti sekarang; tampilkan di panel hanya status (aktif/kedaluwarsa/terkunci, tanggal, jumlah komentar tamu). Menyimpan token terbaca butuh perubahan skema; **tidak direkomendasikan**: buat tautan baru bila hilang. |
| P5 | Alamat halaman publik | `/tinjau/dcs/{token}` dan alihan `/?dcsReviewToken=`. Apakah `DCS_SHARE_BASE_URL` perlu diubah (domain publik berbeda dari aplikasi internal)? |
| P6 | Rute `ai/extract-dcs-metadata` dan `ExtractMetadataController` | Hapus di Fase E bersama API lama (padanan web sudah ada). |
| P7 | Tes yang hanya menguji API lama (file di `tests/Feature/Http/Controllers/Dcs/*`, `Middleware/AuthenticateNodeSessionTest`, `Support/Dcs/DcsErrorRendererTest`) | Daftarkan satu per satu di Fase E dan minta persetujuan sebelum menghapus; skenario yang belum punya padanan di tes web (misalnya `AiAndAppendixTest`, `VersionAuthoringTest`) saya pindahkan ke tes web lebih dulu. |
| P8 | `tests/golden/capture.sh` | Tetap, karena ia merekam API Node (bukan Laravel); tidak terkait jembatan. |
| P9 | Perintah impor/ekspor dan koneksi `legacy` | Tidak disentuh, sesuai tugas. |

**Urutan kerja setelah persetujuan:** B (revisi baru, lepas nomor), C (jadwal tinjau ulang, terjemah), D (tautan eksternal + halaman publik + alihan), lalu E (penghapusan) hanya bila P1 sudah terjawab dan syarat (1)-(3) terpenuhi.

## Yang belum diverifikasi di Fase A
- Panggilan sebenarnya dari tiap layar React tidak dijalankan; daftar di atas dari pembacaan kode (`grep` dan membaca fungsi), bukan pengujian di browser.
- Apakah ada klien lain di luar repo (skrip, integrasi, tautan tersimpan di surel) yang memakai `/api/dcs/*` tidak bisa dipastikan dari kode; periksa log akses proxy sebelum Fase E.
