# 06 — Fase C4: saran AI untuk pasal ("Tulis draf pasal dengan AI")

Tanggal: 2026-11-20. Persetujuan: "Setuju semua" untuk Q-T7 (`01-audit.md`: hanya "tulis draf pasal", izin `templates.manage`, prompt hanya dari deskripsi pengguna, hasil hanya mengisi form, lewat `app/Support/Ai`). Lingkup: Action dan prompt, tanpa controller, route, atau UI (C6). Tidak ada perubahan skema.

## 1. Dikerjakan

- **`App\Actions\Templates\DraftClauseWithAi`**: menyarankan satu pasal dari deskripsi. **Tidak menyimpan apa pun.** Pengguna membaca, mengedit, lalu menyimpan lewat `SaveContractClause`.
  - Izin: `ContractClause` `create` (`templates.manage`). Pengguna dari PT lain dijawab 404; tanpa izin, 403.
  - Keluaran diperiksa: JSON dengan kunci `title`, `body`, `reasoning`; `body` teks biasa; kode placeholder harus ada di daftar kolom isian aktif perusahaan atau token sistem.
  - Masalah yang ditemukan dikembalikan sebagai **peringatan** (HTML dibuang, placeholder asing, placeholder rusak), bukan galat. Teks tetap bisa dibaca; penyimpanan berikutnya tetap ditolak sampai diperbaiki oleh penjaga C3.
- **`App\Support\Templates\ClauseDraftPrompt`**: menyusun prompt.
  - Prompt hanya berisi instruksi tetap (config `contract_templates.ai.system`), nama kolom isian dari **daftar bawaan global** (sama untuk semua PT), dan deskripsi pengguna.
  - Deskripsi dibungkus `<permintaan>…</permintaan>` dan dibersihkan (karakter kontrol dibuang; penanda itu sendiri dihapus). Instruksi menyatakan bahwa isi blok adalah DATA.
- **Config** `contract_templates.ai`: tahap (`contract_clause_draft`), batas deskripsi (1000 karakter), batas alasan (500), instruksi sistem, dan tugas. Pesan baru: `ai_description_required`, `ai_description_long`, `ai_unavailable`, `ai_invalid`, `ai_html_removed`, `ai_placeholder_unknown`.

**Keputusan teknis yang perlu Anda tahu:** `StructuredGenerator` tidak dipakai. Ia mencatat setiap panggilan ke `ai_calls`, yang kolom `tenant_id`-nya wajib diisi dan hanya diisi dari perbandingan dokumen; untuk saran pasal tidak ada perbandingan, sehingga insert akan gagal. Mengubah tabel atau jalur AI bersama tidak diminta, jadi Action memakai `LlmManager` langsung dengan validasi dan **satu kali percobaan ulang** sendiri. Panggilan dicatat ke audit log (penyedia, model, token, jumlah karakter deskripsi, jumlah peringatan), **bukan** isinya. Log aplikasi hanya memuat detail galat penyedia untuk `LlmException`.

## 2. Bukti

- **Tes baru (12)**, `tests/Feature/Templates/ClauseDraftAiTest.php`:
  - saran dikembalikan tanpa menyimpan apa pun; audit memuat jumlah karakter dan angka panggilan, tanpa teks deskripsi;
  - peran `hr_ga` ditolak dengan `AuthorizationException`, dan model tidak dipanggil;
  - PT lain menjawab 404;
  - prompt **tidak memuat** nama PT (`PT Rahasia Uji`) atau kode kolom isian perusahaan (`KodeRahasiaPerusahaan`);
  - instruksi yang disisipkan dalam deskripsi, termasuk `</permintaan>` palsu, tidak bisa menutup blok data (penanda hanya muncul sekali);
  - karakter kontrol dibuang sebelum dikirim;
  - HTML di keluaran dibuang dan peringatan dicatat (`<script>` tidak tersisa sebagai tag);
  - placeholder asing dan bentuk rusak menjadi peringatan, teks tetap utuh;
  - balasan tidak valid pertama lalu valid kedua: berhasil; dua kali tidak valid: pesan `ai_invalid`;
  - model tidak terjangkau: pesan `ai_unavailable`, detail penyedia (503) tidak bocor ke pesan;
  - deskripsi kosong (hanya spasi dan NUL) dan lebih dari 1000 karakter ditolak sebelum model dipanggil.
- **Perbaikan tes dari C2:** `SaveLibraryTest` membaca catatan audit tanpa `orderBy('id')`. PostgreSQL mengembalikan urutan lain dan tes gagal; SQLite kebetulan lulus. Ditambahkan `orderBy('id')`. Tidak ada tes lain yang memakai pola itu.
- **Suite penuh** (`php -d memory_limit=512M`): **PostgreSQL 1704/1704** (dijalankan ulang setelah perbaikan); **SQLite 1690 lulus, 14 dilewati** (tes khusus PostgreSQL).
- Pint lulus. PHPStan bersih untuk `app/Actions/Templates` dan `app/Support/Templates`.
- Ukuran: `DraftClauseWithAi.php` 149 baris, `ClauseDraftAiTest.php` 137; tidak ada yang > 200.

## 3. Beda dari Node dan dari dokumen

- Node memakai `ai.models.generateContent` (Gemini) dengan prompt yang menyisipkan deskripsi tanpa penanda; hasilnya langsung dipakai di form. Di sini deskripsi dibungkus penanda, dibersihkan, dan keluaran divalidasi.
- Node tidak mencatat pemanggilan AI di audit log. Di sini ada catatan tanpa isi.
- Node memakai **tag** pasal yang dihasilkan AI. Q-T6 membuang tag, jadi tidak dihasilkan.
- "Saran AI" pada wizard kontrak (Node `/api/ai/suggest-template`) tidak dibuat; itu milik modul kontrak (Q-T7).

## 4. Konflik dokumen vs kode

- Dokumen tidak menyebut `ai_calls`; kode mewajibkan `tenant_id` untuk setiap panggilan. Keputusan di atas mengikuti kode.

## 5. Risiko dan yang belum diverifikasi

- **Deskripsi pengguna dikirim ke penyedia AI.** Deskripsi kebutuhan pasal umumnya tidak berisi data pribadi, tetapi pengguna bisa mengetik apa saja. Perlu teks peringatan di UI (C6): jangan mengetik nama orang, nomor identitas, rekening, atau gaji. Belum ada aturan teknis yang memeriksanya. Ini pertanyaan 1.
- **Kualitas hukum tidak dijamin.** Saran harus ditinjau tim Legal sebelum dipakai; teks ini tidak boleh dianggap sebagai nasihat hukum. Perlu catatan di UI.
- **Biaya dan batas laju** belum dibatasi. Route di C6 perlu `throttle` seperti perbandingan dokumen (`throttle:20,1`).
- **Tidak ada pemanggilan nyata** ke Gemini atau DeepSeek di sini; semua tes memakai `FakeLlmClient`. Kualitas dan format balasan penyedia asli belum diuji.
- **Perilaku model yang tidak patuh** (mis. tetap menulis HTML atau placeholder baru) ditangani oleh peringatan, bukan oleh pemaksaan. Ini disengaja.
- Pemeriksaan browser belum dilakukan (belum ada halaman).

## 6. Temuan di luar lingkup

- `StructuredGenerator` mewajibkan `tenant_id` di `ai_calls`, padahal tidak semua panggilan AI punya perbandingan. Ini akan menghalangi fitur AI lain yang tidak terkait perbandingan. Dicatat untuk perbaikan terpisah.

## 7. Pertanyaan terbuka

1. **Peringatan di UI dan pengaturan data:** setujui teks peringatan "jangan mengetik data pribadi atau rahasia" di layar saran AI, dan apakah perlu pemeriksaan teknis (mis. menolak deskripsi yang tampak seperti NIK atau nomor rekening)? Rekomendasi: teks peringatan saja di C6; pemeriksaan teknis tidak dibuat sekarang, karena menyentuh aturan data pribadi. [Keyakinan sedang]
2. **Batas laju:** rekomendasi 20 saran per menit per pengguna, seperti perbandingan dokumen. [Keyakinan sedang]
3. **Lanjut ke C5** (impor dari Node: klausul, template, kolom isian, dengan `--dry-run`, semua-atau-tidak, dan fixture sintetis)? Rekomendasi: ya. [Keyakinan tinggi]

## Berkas

Dibuat: `app/Actions/Templates/DraftClauseWithAi.php`, `app/Support/Templates/ClauseDraftPrompt.php`, `tests/Feature/Templates/ClauseDraftAiTest.php`, `docs-tracked/vendor-template/06-c4-saran-ai.md`.

Diubah: `config/contract_templates.php` (`ai`, enam pesan), `tests/Feature/Actions/Templates/SaveLibraryTest.php` (urutan audit), `docs-tracked/vendor-template/PROGRESS.md`.
