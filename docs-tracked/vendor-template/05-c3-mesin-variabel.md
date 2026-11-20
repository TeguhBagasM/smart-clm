# 05 — Fase C3: mesin variabel (placeholder, validasi, pratinjau)

Tanggal: 2026-11-20. Lingkup sesuai instruksi: parsing dan validasi placeholder, pratinjau dengan data contoh, aman dari injeksi, dengan tes input berbahaya. Tanpa controller, route, atau UI (C6). Tanpa saran AI (C4, menunggu "lanjut"). Tidak ada perubahan skema.

## 1. Dikerjakan

**Aturan placeholder** (audit A2.5): hanya `{{Kode}}`, kode satu kata huruf/angka/garis bawah diawali huruf, paling panjang 60 karakter. Tidak ada ekspresi, filter, pemanggilan fungsi, atau sintaks lain. Tanda `{` tunggal dan teks biasa dibiarkan.

| Kelas | Berkas | Isi |
|---|---|---|
| Parser pure | `app/Support/Templates/Placeholders.php` | `codes()` (kode valid, unik, berurutan), `invalidTokens()` (`{{ Nama }}`, `{{a\|upper}}`, `{{}}`), `hasStrayBraces()` (`{{` atau `}}` yang tersisa). Tanpa konfigurasi dan tanpa database. |
| Penjaga | `app/Support/Templates/PlaceholderGuard.php` | `known()` menggabungkan kolom isian dengan **token sistem** dari config; `ensureUsable()` menolak bentuk rusak lebih dulu, lalu kode di luar daftar yang diizinkan, dengan pesan yang menyebut kodenya. |
| Renderer | `app/Support/Templates/PlaceholderRenderer.php` | `segments()` memotong teks menjadi bagian biasa dan kolom isian (nilai, dan bendera `missing`); `plain()` mengganti dalam **satu lintasan**. Nilai tidak pernah dibaca ulang. Tidak ada HTML yang dibentuk di server. |
| Pratinjau | `app/Support/Templates/TemplatePreview.php` | `for(TemplateVersion)`: pembuka, pihak, pasal (segmen), penutup, adendum, kolom isian dengan contoh, dan daftar `missing`. Hanya membaca. |

**Token sistem** (`config/contract_templates.php` `system_tokens`): 13 token yang diisi modul kontrak saat kontrak dibuat (`Party1Name` … `PreviousDate`), dengan sumber Node (`src/App.tsx:13519-13522`, `src/types.ts:161-175`) dan contoh fiktif. Dibolehkan di semua teks.

**Contoh per jenis** (`samples`): teks `Contoh teks`, angka `1.250.000`, tanggal `1 Januari 2027`. Kolom isian yang punya `default_value` memakai nilai itu.

**Penjaga dipasang di Action C2:**
- `SaveContractClause`: isi pasal hanya boleh memakai kode **kolom isian aktif milik PT** atau token sistem.
- `SaveTemplateDraft`: pembuka, penutup, adendum, dan **setiap salinan pasal** hanya boleh memakai kolom isian **yang dipilih untuk draf itu** atau token sistem. Pemeriksaan dilakukan di dalam transaksi, setelah kolom isian yang dipilih diketahui. Hasilnya: `fieldVariables()` sekarang mengembalikan daftar kode.
- Pesan galat di `config/contract_templates.php` (`token_invalid`, `token_stray`, `placeholder_unknown`, `placeholder_not_selected`).

## 2. Bukti

- Tes baru (41):
  - `tests/Unit/Templates/PlaceholdersTest.php`: kode valid dan urutannya; tanda `{` tunggal dibiarkan; **20 kelas input berbahaya atau rusak** (spasi, `__class__`, `7*7`, `$x`, `a|upper`, `'x'`, `system("ls")`, titik, kosong, bersarang, `}}` berlebih, tak tertutup, `}}` sendirian, `{{` sendirian, 61 karakter, baris baru di dalam, byte null, huruf Cyrillic yang mirip, angka di depan, dan HTML yang disisipi); batas 60 dan 61 karakter.
  - `tests/Unit/Templates/PlaceholderRendererTest.php`: segmen dengan bendera `missing`; nilai yang berisi `{{Rahasia}}` atau `<script>` tetap sebagai data; penggantian satu lintasan (`{{A}}` berisi `{{B}}` tidak menarik kolom `B`); bentuk rusak dibiarkan literal.
  - `tests/Feature/Templates/PlaceholderGuardActionTest.php` (integrasi Action): pasal dengan kolom sendiri dan token sistem diterima; kolom tidak dikenal dan kolom nonaktif ditolak dengan nama kodenya; bentuk rusak ditolak (spasi, ekspresi, pemanggilan, tak tertutup, bersarang); draf menolak pasal atau pembuka yang memakai kolom tidak dipilih, dan menerima setelah kolomnya dipilih; pembuka rusak ditolak dan revisi draf tidak naik.
  - `tests/Feature/Templates/TemplatePreviewTest.php`: template bawaan terisi contoh (`ContractValue` = `1.250.000`, `Currency` = `IDR`, tidak ada yang hilang); token sistem di pembuka terisi; placeholder tanpa jawaban tampil `[Hantu]` dan masuk `missing`; nilai default yang berbentuk HTML dikembalikan apa adanya sebagai data.
- Suite penuh (`php -d memory_limit=512M`): **PostgreSQL 1692/1692**; **SQLite 1678 lulus, 14 dilewati** (tes khusus PostgreSQL). 0 gagal. Tes C2 yang sudah ada tetap lulus setelah penjaga dipasang.
- Pint lulus. PHPStan bersih untuk `app/Support/Templates` dan `app/Actions/Templates`.
- Ukuran: terbesar `SaveTemplateDraft.php` 184 baris, `SeedTenantTemplateLibrary.php` 180; tidak ada yang > 200.

## 3. Beda dari Node dan dari dokumen

- Node mengganti `{{Kunci}}` dengan regex longgar (`\{\{([^}]+)\}\}`), jadi `{{ Nama }}` atau `{{a.b}}` lolos sebagai kunci tak dikenal. Di sini ditolak di simpan dengan pesan perbaikan.
- Node menampilkan kunci kosong sebagai `[Kunci]` merah di layar. Pratinjau di sini memakai contoh, dan bentuk `[Kode]` hanya untuk yang tak terjawab.
- Node membiarkan HTML dan placeholder bercampur; di sini teks pasal biasa dan placeholder selalu divalidasi.

## 4. Konflik dokumen vs kode

Tidak ada. Audit A2.5 (sintaks placeholder) dan kode C3 sejalan.

## 5. Risiko dan yang belum diverifikasi

- **Token sistem global di config.** Node juga menyimpannya global. Bila token ini sebaiknya per PT atau per modul, itu keputusan baru (pertanyaan 1).
- **Pasal yang memakai kolom yang kemudian dinonaktifkan** tidak bisa disimpan lagi sampai kolomnya diaktifkan atau dihapus dari teks. Pesannya menjelaskan langkahnya. Ini pilihan yang disengaja, tetapi berdampak pada pengguna.
- **Pratinjau hanya memakai contoh.** Nilai nyata dari kontrak belum ada; itu menunggu modul kontrak.
- **Tidak ada keluaran HTML atau PDF di sini.** Pemakai (web via Vue, dan PDF kelak) wajib memperlakukan segmen sebagai teks. Tes memastikan nilai berbentuk HTML tidak berubah di server, tetapi penyaringan di klien tetap dibutuhkan.
- **Pratinjau belum ada endpoint.** Ia dipanggil lewat controller di C6.
- **Pemeriksaan browser belum dilakukan** karena belum ada halaman.
- DB dev belum dimigrasi untuk tabel C1 (dijadwalkan C6).

## 6. Temuan di luar lingkup

- Tidak ada baru selain temuan PHPStan lama di `LookupListSpec.php` (sudah dicatat di C2).

## 7. Pertanyaan terbuka

1. **Token sistem tetap global (config) atau per PT?** Rekomendasi: global, karena modul kontrak yang mengisinya juga global dan sama dengan Node. [Keyakinan sedang]
2. **Pasal yang memakai kolom nonaktif:** tetap diblokir sampai diperbaiki (sekarang), atau boleh disimpan dengan peringatan? Rekomendasi: tetap diblokir, karena pesan langsung menyebut cara memperbaikinya. [Keyakinan sedang]
3. **Lanjut ke C4** (saran AI "tulis draf pasal", hanya prompt dari deskripsi pengguna, hasil hanya mengisi form, lewat `app/Support/Ai`)? Rekomendasi: ya. Persetujuan "setuju semua" sudah mencakup Q-T7. [Keyakinan tinggi]

## Berkas

Dibuat: `app/Support/Templates/{Placeholders,PlaceholderGuard,PlaceholderRenderer,TemplatePreview}.php`, `tests/Unit/Templates/{PlaceholdersTest,PlaceholderRendererTest}.php`, `tests/Feature/Templates/{PlaceholderGuardActionTest,TemplatePreviewTest}.php`, `docs-tracked/vendor-template/05-c3-mesin-variabel.md`.

Diubah: `app/Actions/Templates/SaveContractClause.php` (penjaga isi pasal), `app/Actions/Templates/SaveTemplateDraft.php` (penjaga draf; `fieldVariables()` mengembalikan kode), `config/contract_templates.php` (`system_tokens`, `samples`, empat pesan baru), `docs-tracked/vendor-template/PROGRESS.md`.
