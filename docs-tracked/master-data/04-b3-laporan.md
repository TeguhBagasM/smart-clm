# 04 — Laporan Blok B3: opsi bergantung

Dibuat 2026-11-20. Tanpa UI, controller, atau route. B4 belum dikerjakan.

## 1. Dikerjakan (`app/Support/MasterData`)
- **`DependentOptions`**: pilihan untuk formulir satu PT, hanya baris aktif, urut sesuai urutan PT. Aturan mengikuti Node apa adanya (audit P5):
  - **Platform → Mitra**: grup yang menyebut platform itu dipakai; kalau tidak ada, grup yang berlaku untuk semua platform. Grup dan mitra tersembunyi dibuang, nama kembar dibuang. Platform asing, tersembunyi, atau tidak ada menghasilkan daftar kosong.
  - **Jenis surat ← modul dan kategori**: jenis eksternal/karyawan/keduanya; jenis tanpa kategori cocok untuk semua kategori; dokumen eksternal tidak menampilkan jenis yang hanya milik kategori karyawan.
  - Kategori (eksternal tanpa kategori karyawan, karyawan hanya kategori karyawan), hubungan kerja, tipe pihak, mata uang, dan departemen (DCS, hanya dibaca, urut nama).
- **`ClassificationGuard`** (`external()` dan `employee()`): setiap id yang dikirim harus ada di opsi saat itu. Teks bebas, id PT lain, baris tersembunyi, jenis dengan modul salah, mitra yang tidak ditawarkan untuk platformnya, dan jenis di luar kategori ditolak sekaligus, dengan pesan "Pilih dari daftar. Kalau belum ada, minta admin menambahkannya di Master Data." Tidak ada pilihan "Lainnya (isi manual)".
- **`ActingCompany`**: pengguna PT dikunci ke PT-nya sendiri apa pun isi permintaan; super admin wajib memilih PT aktif.
- Rantai yang diminta ("PT → hubungan kerja → jenis surat → divisi") ada di `employee()`. Rantai eksternal Node (PT → jenis → platform → mitra) ada di `external()`.
- CLAUDE.md diperbarui.

## 2. Bukti
- 14 tes baru (`DependentOptionsTest`, `ClassificationGuardTest`), lulus: aturan platform→mitra (eksplisit vs semua platform, tersembunyi, duplikat, platform asing), jenis×modul×kategori, departemen DCS dan isolasi PT, nol pilihan "Lainnya" di semua daftar yang di-seed, jumlah query tidak naik meski baris bertambah (tes membandingkan 3 mitra vs 43 mitra dan 20 jenis tambahan), penolakan teks bebas/PT asing/baris tersembunyi/modul salah, PT dikunci.
- Suite penuh: PostgreSQL 1382/1382 lulus; SQLite 1369 lulus, 13 dilewati (`php -d memory_limit=512M`).
- Pint lulus. PHPStan: 0 error di berkas master data. Tidak ada berkas melebihi 200 baris (`DependentOptions` 136, `ClassificationGuard` 97, `ActingCompany` 28).
- Tidak ada skema baru, jadi tidak ada EXPLAIN baru. Query opsi memakai indeks B1 (`tenant_id` pertama pada tiap tabel, `platform_id` pada tabel penghubung grup-platform).

## 3. Beda dari Node dan dari dokumen
- Node mengirim seluruh blob master data ke semua pengguna dan menyaring di browser; di sini penyaringan di server dan hanya PT sendiri.
- Node membuang entri bernama "Lainnya" dan menambahkan pilihan manual; di sini pilihan manual tidak ada dan guard menolak nilai di luar daftar.
- `01-audit.md` §9 P5 diikuti: "hubungan kerja → jenis surat" tidak dibuat sebagai ketergantungan, karena Node tidak membatasinya.

## 4. Konflik dokumen vs kode
- Tidak ada baru. Instruksi menulis rantai "PT → hubungan kerja → jenis surat → divisi"; kode Node hanya punya rantai itu untuk karyawan dan tanpa ketergantungan antar langkah (`src/App.tsx:1361-1435`).

## 5. Risiko dan yang belum diverifikasi
- Belum ada controller/route/halaman yang memanggil layanan ini; belum teruji lewat HTTP.
- Perilaku di Node untuk jenis dengan kategori `Employment` bergantung pada nama; di sini pada penanda `is_employee_key` yang di-seed dan diisi importer. Kalau PT tidak punya baris bertanda itu, daftar kategori karyawan kosong.
- Mitra dari grup tanpa platform hanya muncul bila tidak ada grup yang menyebut platform itu secara eksplisit (meniru Node); ini bisa mengejutkan admin dan perlu teks bantuan di UI.
- Departemen dibaca dari DCS tanpa `sort_order`; urutan = nama. Departemen Node yang belum pernah dipakai dokumen DCS mungkin belum ada di Laravel.
- Suite PHP sekarang melewati batas memori bawaan 128 MB di SQLite maupun PostgreSQL; saya menjalankan dengan `memory_limit=512M`.

## 6. Temuan di luar lingkup
- `composer test` dan `composer test:pg` perlu `memory_limit` lebih besar (lihat risiko).
- ±105 error PHPStan lama di kode DCS.

## 7. Pertanyaan terbuka
1. **Tambah `memory_limit` pada skrip `test` dan `test:pg` di `composer.json`?** Rekomendasi: ya, karena tes gagal tanpa keterangan jelas saat memori habis. [Keyakinan tinggi]
2. **Perlu pilihan "tidak ada mitra" yang eksplisit bila platform tidak punya mitra?** Sekarang mitra opsional dan daftar kosong. Rekomendasi: ya, di UI saja. [Keyakinan sedang]
3. **Controller dan route**: masih menunggu keputusan dari B2 (rekomendasi: bersama fase UI). [Keyakinan sedang]
