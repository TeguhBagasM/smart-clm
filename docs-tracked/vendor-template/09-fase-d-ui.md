# 09 — Fase D: UI Klausul & Template (Vue + Inertia)

Tanggal: 2026-11-20. Cakupan: halaman Klausul, Kolom isian, Templat, editor draf, pratinjau, riwayat versi, perpustakaan klausul, dan menu "Klausul & Template" aktif.

## 1. Ringkasan

- Halaman berikut sudah ada dan terhubung ke backend fase C6: `contract-library/clauses`, `contract-library/fields`, `contract-library/templates`, dan `contract-library/templates/{id}` (editor).
- Menu sidebar "Klausul & Template" (Master Data) aktif, tampil hanya bagi pengguna yang boleh `viewAny` templat.
- Editor: simpan draf (dengan `draft_revision`), susun ulang pasal (drag atau tombol naik/turun), hapus pasal, centang kolom isian wajib, sisipkan dari perpustakaan, pratinjau, riwayat versi, terbitkan (dengan konfirmasi), salin templat, dan mulai draf baru.
- Verifikasi di peramban terbatas (lihat §4). Banyak skenario manual belum dicoba; daftarnya ada di §5.

## 2. Perubahan di fase ini

Frontend (baru):
- `resources/js/pages/contract-library/{Clauses,Fields,Templates,TemplateEditor}.vue`
- `resources/js/components/contract-library/` (klausul, kolom isian, templat, editor, pratinjau, riwayat, perpustakaan)
- `resources/js/composables/useDraftEditor.ts`, `resources/js/lib/{libraryRequest,contractLibrary}.ts`, `resources/js/types/contract-library.ts`
- `tests/js/contract-library/{contractLibrary,components}.test.ts`

Perbaikan dalam sesi verifikasi ini:
- `resources/js/components/contract-library/editor/DraftForm.vue`: grid induk diberi `grid-cols-1` di bawah `lg`. Tanpa itu, di 375 px kolom melebar 16 px melewati tepi layar (kotak teks ikut terpotong). Setelah perbaikan tidak ada elemen yang melewati tepi.

Backend fase C6 tidak diubah pada fase D.

## 3. Hasil verifikasi

| Pemeriksaan | Hasil |
|---|---|
| Tes JS (`npx vp test run`) | 17 berkas, 124 tes lulus |
| Type-check (`npx vue-tsc --noEmit`) | Tanpa error |
| Lint berkas yang disentuh (`npx vp lint`) | Tanpa keluaran (bersih) |
| Build (`npm run build`) | Berhasil |
| Tes PHP PostgreSQL (`vendor/bin/pest --configuration=phpunit.pgsql.xml`) | 1758 dari 1758 lulus |
| Tes PHP SQLite (`vendor/bin/pest`) | 1744 lulus, 14 dilewati (sama seperti fase sebelumnya) |
| `FileSizeTest` (semua berkas Vue/TS ≤ 200 baris) | Lulus |
| `ThemeTest` (kontras WCAG AA, token warna) | Lulus |

Catatan: `php artisan test` gagal karena batas memori 128 MB dan fatal pada plugin Pest TIA. Keduanya dijalankan lewat `php -d memory_limit=-1 vendor/bin/pest`. Penyebab batas memori pada `artisan test` belum diselidiki.

## 4. Verifikasi di peramban (Browser pane, templat 5 di dev)

Sudah dicoba:
- Editor memuat draf: judul, badge, tombol aksi, kalimat pembuka, pihak, perpustakaan klausul.
- Simpan draf sukses: `draft_revision` 3 → 4, teks tersimpan di DB (`contract_template_versions`), notice "Semua perubahan sudah tersimpan.".
- Konflik revisi: halaman lama yang disimpan menampilkan "Draf ini sudah diubah orang lain. Muat ulang halaman…" di bilah simpan. Setelah muat ulang, draf kembali konsisten.
- Simpan dengan placeholder tidak dikenal: galat server tampil di bilah simpan dalam bahasa Indonesia biasa (dicoba di sesi sebelumnya).
- Lebar 375 px: tidak ada scroll horizontal dan tidak ada elemen melewati tepi (setelah perbaikan §2).
- Lebar pane desktop (sekitar 800 px): tata letak dua kolom tampil benar.

Belum dicoba di sesi ini:
- Lebar 768 px dan 1280 px penuh, serta mode gelap.
- Daftar Klausul, Kolom isian, dan Templat (termasuk tampilan kartu di ponsel).
- Sisipkan pasal dari perpustakaan, naik/turun, hapus, centang wajib pada kolom isian.
- Dialog terbitkan (konfirmasi dampak), pratinjau, riwayat versi, nonaktifkan, salin templat, mulai draf.

Permintaan tak terduga yang terlihat di log sesi sebelumnya (`PUT /contract-library/templates/5` → 422 "name wajib diisi.") tidak muncul lagi setelah muat ulang. Asalnya belum dapat dijelaskan.

## 5. Daftar cek manual (ponsel 375 px)

Belum dijalankan semuanya:
- [ ] Daftar klausul, kolom isian, dan templat tampil sebagai kartu, dengan cari dan filter.
- [ ] Tombol utama selebar layar, target sentuh minimal 44 px.
- [ ] Sheet formulir (klausul, kolom isian, templat) layar penuh.
- [ ] Editor: bilah simpan tetap di bawah dan tidak menutupi isi.
- [ ] Perpustakaan klausul bisa dicari dan disisipkan.
- [ ] Dialog terbitkan dan dialog nonaktifkan terbaca penuh.
- [ ] Tab (Isi draf, Pratinjau, Riwayat versi) bisa digeser ke samping.

## 6. Paritas Node vs Laravel

Belum disusun tabel paritas penuh. Perlu membaca halaman Node Klausul & Template di `management-contract` lalu mencocokkan alur dan teks. Ini langkah tertunda.

## 7. Temuan di luar cakupan dan pertanyaan terbuka

Temuan di luar cakupan (sudah dicatat sebelumnya):
- Binder global `record` di `routes/web.php` bentrok dengan route library (sudah diakali di `LibraryFormRequest::record()`, perlu perbaikan terpisah).
- Pola `<Alert><p>` di master data juga terlipat seperti yang terjadi di library.
- `StructuredGenerator` tidak bisa dipakai karena `ai_calls.tenant_id` NOT NULL (library memakai `LlmManager` langsung).
- Masalah keamanan Node dari audit: HTML disimpan tanpa sanitasi, PUT vendor rawan mass assignment, prompt AI rentan injeksi, GET vendor membuka data rekening.
- Batas memori 128 MB pada `artisan test` (perlu diselidiki).

Pertanyaan terbuka untuk pengguna:
- Rollback templat terbit ditolak sekarang; apakah cukup "sembunyikan"?
- Token sistem bersifat global; setuju?
- Pasal terlindungi hanya `templates.manage`; perlu batasan tambahan?
- Peringatan privasi dan konfirmasi batas laju AI.
- Dry run impor terhadap salinan database Node sebelum impor sungguhan.
