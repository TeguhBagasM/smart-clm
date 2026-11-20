# 08 — Fase C6: controller, route, dan tes HTTP (Klausul & Template)

Tanggal: 2026-11-20. Lingkup: lapisan HTTP saja. Controller tipis, route bernama, Inertia untuk halaman, JSON untuk pratinjau dan saran AI, serta tes HTTP. **Halaman Vue belum dibuat** (fase D); route halaman akan gagal di peramban sampai D selesai, dan tes memakai `inertia.testing.ensure_pages_exist=false`. Menu sidebar belum diaktifkan (D).

Ikut dikerjakan atas persetujuan C1 (pertanyaan 4): **migrasi dan isi bawaan di DB dev**.

## 1. Dikerjakan

**Route** (`routes/contract-library.php`, dikait dari `routes/web.php` agar berkas itu tetap di bawah 200 baris). 23 route bernama `contract-library.*`, semuanya di belakang `auth`:

- Klausul: `clauses.index|store|update|deactivate|reactivate`, `clauses.draft` (saran AI, `throttle:20,1`).
- Kolom isian: `fields.index|store|update|deactivate|reactivate`.
- Templat: `templates.index|store|show|update|deactivate|reactivate|duplicate`, `templates.draft.start` (POST), `templates.draft.save` (PUT), `templates.draft.order` (PUT), `templates.preview` (GET JSON), `templates.publish` (POST).

**Controller** (`app/Http/Controllers/Templates`, masing-masing tipis: Form Request, lalu Action, lalu toast atau Resource):
- `ClauseController`, `VariableController`, `TemplateController`, `TemplateDraftController`, `ClauseDraftController` (JSON).
- Konsern `FlashesLibraryToast` (teks toast dari config `contract_templates.toast`) dan `ResolvesCompany` (dipakai ulang dari master data untuk perusahaan dan halaman 403 yang ramah).
- `App\Support\Templates\LibraryOptions`: opsi pilihan perusahaan (kategori, kolom isian aktif, pasal aktif). Hanya nama dan kode yang keluar.
- Daftar memakai ListSpec dari C2 dan `withCount` untuk jumlah pemakaian.

**Perubahan yang perlu Anda tahu (ditemukan saat tes):**
1. **Binder `record` master data bentrok.** `routes/web.php` mendaftarkan `Route::bind('record', …)` yang membaca segmen kedua URI. Untuk `contract-library/clauses/{record}` segmennya `clauses`, sehingga setiap rute ber-`record` menjawab 404. Perbaikannya di sisi library: parameter route diberi nama sendiri (`{clause}`, `{field}`, `{template}`), dan `LibraryFormRequest::record()` menemukan model dari parameter route apa pun. **Binder master data tidak diubah.** Catatan: `record` di master data tetap perlu dipisah suatu saat (temuan di luar lingkup).
2. Daftar memakai builder Eloquent dari `getQuery()` pada relasi (seperti controller master data); tanpa itu `ListSpec` menerima `HasMany` dan gagal.

**Migrasi dan isi DB dev** (`smart_clm`): 7 migrasi C1 dijalankan (tanpa rollback; tidak ada tabel yang tertunda). `contract-templates:seed-defaults --dry-run` lalu sungguhan untuk perusahaan DEMO: 20 kolom isian, 8 pasal, 3 templat **draf**. Tidak ada data lain yang disentuh.

## 2. Bukti

**Tabel permukaan** (route → izin → data yang dikembalikan; 403 = izin kurang, 404 = PT lain):

| Route (nama) | Izin | Data yang dikembalikan / diubah |
|---|---|---|
| `GET contract-library/clauses` (`clauses.index`) | `viewAny` klausul (`templates.manage`) | Inertia `contract-library/Clauses`: `items` (ContractClauseResource + `usage_count`, `can`), `list`, `info`, `company`, `companies` |
| `POST clauses` | `create` | simpan pasal; toast; redirect back |
| `PUT clauses/{clause}` | `update` (PT lain 404) | simpan; `revision` naik bila isi berubah |
| `PATCH clauses/{clause}/deactivate` / `reactivate` | `deactivate` / `reactivate` (PT lain 404) | sembunyikan (tanpa hapus) dengan jumlah pemakaian di toast |
| `POST clauses/draft-with-ai` | `create` (throttle 20/menit) | JSON `{title, body, reasoning, warnings}`; **tidak menyimpan** |
| `GET fields` (`fields.index`) | `viewAny` kolom isian | `items` (+ `usage_count`), `list`, `info.sources` |
| `POST fields`, `PUT fields/{field}`, `PATCH …/deactivate|reactivate` | `create`/`update`/`deactivate`/`reactivate` | simpan (kode tak bisa diganti bila dipakai); sembunyikan |
| `GET templates` (`templates.index`) | `viewAny` templat | `items` (dengan versi terbit, draf, `can`), `list`, `info` |
| `POST templates` | `create` | buat + draf 1; redirect ke editor |
| `GET templates/{template}` (`templates.show`) | `update` (hr_ga 403, PT lain 404) | Inertia `contract-library/TemplateEditor`: `template`, `version` (draf atau terbit, dengan pasal, pihak, kolom), `versions`, `options` (pasal dan kolom aktif), `company` |
| `PUT templates/{template}` | `update` | nama, deskripsi, kategori; tidak menyentuh versi |
| `PATCH templates/{template}/deactivate` / `reactivate` | `deactivate` / `reactivate` | sembunyikan / tampilkan |
| `POST templates/{template}/duplicate` | `duplicate` | salin; redirect ke salinan |
| `POST templates/{template}/draft` | `startDraft` | draf baru dari versi terbit; galat bila sudah ada draf |
| `PUT templates/{template}/draft` | `saveDraft` | simpan draf; revisi usang → galat `draft_revision` |
| `PUT templates/{template}/draft/order` | `saveDraft` | urutan pasal draf; daftar tak lengkap → galat |
| `GET templates/{template}/preview` | `update` | JSON pratinjau dengan contoh (draf, atau terbit bila tidak ada draf) |
| `POST templates/{template}/publish` | `publish` | terbitkan (butuh pasal dan pihak) |

**Tes HTTP** (`tests/Feature/Http/Controllers/Templates`, 37 tes, semuanya lulus):
- `ClauseControllerTest` (8): tamu diarahkan ke login; hr_ga 403 dengan halaman ramah dan semua tulis ditolak; PT lain 404; daftar untuk legal dengan `usage_count`, `can`, dan `companies` null; tambah dengan toast dan audit; HTML, judul ganda, dan placeholder asing ditolak dengan pesan yang tepat; ubah menaikkan revisi; sembunyikan dan tampilkan dengan toast.
- `VariableControllerTest` (4): daftar, izin (403 dan 404), tambah dengan toast dan kode tak valid ditolak, sembunyikan dan tampilkan.
- `TemplateControllerTest` (8): daftar dengan `can`; editor dengan draf dan opsi, hr 403, PT lain 404; buat template dan galat nama; ubah nama tanpa menyentuh versi, sembunyikan dan tampilkan; draf kedua ditolak lalu draf baru setelah terbit; duplikat; hr 403 dan PT lain 404.
- `TemplateDraftControllerTest` (6): simpan draf dengan toast, revisi usang ditolak; isi yang salah ditunjuk (pasal tidak ada, placeholder tidak dipilih); urutan dan urutan tak lengkap; terbit dan terbit tanpa pasal; pratinjau JSON (legal), hr 403, PT lain 404; penulisan draf oleh PT lain 404 dan hr 403.
- `ClauseDraftControllerTest` (3): saran JSON tanpa menyimpan; hr 403 dan validasi 422; **batas 429 setelah 20 permintaan**.
- `ContractLibrarySurfaceTest` (9): setiap halaman daftar untuk peran yang tepat (200), peran lain (403), dan tamu (login).
- **Suite penuh** (`php -d memory_limit=512M`): **PostgreSQL 1755/1755**; **SQLite 1741 lulus, 14 dilewati** (tes khusus PostgreSQL). 0 gagal.
- Pint lulus. PHPStan bersih untuk controller, request, dan `app/Support/Templates`.
- Ukuran: terbesar `TemplateController.php` 136 baris, `routes/web.php` 193 baris (dekat batas, catat untuk fase berikutnya); tidak ada berkas yang > 200 baris.

## 3. Beda dari Node dan dari dokumen

- Node memakai satu halaman dengan banyak tab dan modal; di sini halaman per objek (fase D).
- Node menyimpan redaksi di tempat; di sini simpan draf dan terbitkan, dengan kunci revisi.
- Saran AI di Node langsung mengisi form; di sini JSON yang diisi form dan belum tersimpan, sama dengan keputusan C4.

## 4. Konflik dokumen vs kode

- Tidak ada baru.

## 5. Risiko dan yang belum diverifikasi

- **Halaman belum ada.** Rute halaman (`clauses.index`, `fields.index`, `templates.index`, `templates.show`) akan gagal di peramban sampai fase D. Tes memakai `ensure_pages_exist=false`.
- **Binder `record` master data** (lihat bagian 1). Perbaikan permanen ada di luar lingkup.
- **Pemeriksaan browser belum dilakukan.**
- **Penjaga akses diuji lewat tes**, bukan lewat browser. Tombol yang tidak boleh dipakai harus disembunyikan di Vue (`can`), dan server tetap menolak.
- **Pratinjau dan saran AI** dibatasi di sisi server (izin, throttle untuk AI). Pratinjau tidak diberi throttle; tidak ada biaya eksternal, jadi tidak diperlukan.
- Migrasi dan seed dilakukan di DB dev. DB produksi belum disentuh.

## 6. Temuan di luar lingkup

- **Binder `record` di `routes/web.php`** membaca segmen kedua URI dan mengabaikan model tipe-hint. Setiap rute baru yang memakai `{record}` di luar `master-data` akan menjawab 404. Perlu diganti dengan binder yang hanya aktif untuk rute master data (atau nama parameter yang berbeda).
- `LookupListSpec.php` punya dua temuan PHPStan lama (dicatat di C2).

## 7. Pertanyaan terbuka

1. **Lanjut ke fase D** (halaman Vue: daftar, editor, perpustakaan klausul, menu aktif)? Rekomendasi: ya, setelah keputusan berikut. [Keyakinan tinggi]
2. **Perbaikan binder `record`:** ganti sekarang sebagai perbaikan terpisah, atau biarkan karena library sudah menghindarinya? Rekomendasi: perbaiki sebagai pekerjaan terpisah. [Keyakinan sedang]
3. **Batas laju saran AI:** 20 per menit per pengguna (sudah dipasang). Setujui atau ubah? [Keyakinan sedang]

## Berkas

Dibuat: `routes/contract-library.php`, `app/Http/Controllers/Templates/{ClauseController,VariableController,TemplateController,TemplateDraftController,ClauseDraftController}.php`, `app/Http/Controllers/Templates/Concerns/FlashesLibraryToast.php`, `app/Http/Requests/Templates/DraftClauseWithAiRequest.php`, `app/Support/Templates/LibraryOptions.php`, `tests/Feature/Http/Controllers/Templates/{ClauseControllerTest,VariableControllerTest,TemplateControllerTest,TemplateDraftControllerTest,ClauseDraftControllerTest,ContractLibrarySurfaceTest}.php`, `docs-tracked/vendor-template/08-c6-http.md`.

Diubah: `routes/web.php` (satu baris `require`), `app/Http/Requests/Templates/LibraryFormRequest.php` (temukan model dari parameter route), `config/contract_templates.php` (blok `toast`), `docs-tracked/vendor-template/PROGRESS.md`.
