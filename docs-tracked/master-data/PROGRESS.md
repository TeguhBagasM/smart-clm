# PROGRESS — Konfigurasi & Master Data (Node → Laravel)

Diperbarui: akhir F3 (semua blok selesai). Sebelum melanjutkan di sesi baru, baca ulang `CLAUDE.md` (bagian "Master data") dan laporan `01`–`08` di folder ini, terutama `07-backend-final.md` (kontrak) dan `08-f0-laporan.md` (tabel permukaan route).

## Blok selesai
B1–B7, F0, F1, F2, F3. Laporan: `02`–`09` di folder ini (`09-frontend-final.md` memuat tabel paritas, bukti, risiko, dan daftar cek manual di ponsel).
Bukti terakhir: PostgreSQL 1557/1557; SQLite 1544 lulus + 13 dilewati (`php -d memory_limit=512M`); 100 tes JS; `vue-tsc`, `vp lint`, `vp fmt --check` (berkas saya), Pint, `npm run build` bersih; tidak ada berkas php/vue/ts > 200 baris.

## Berikutnya
Tidak ada blok terjadwal. Yang menunggu keputusan atau pengerjaan: lihat Pertanyaan terbuka. Calon pekerjaan: modul Klausul & Template, Dokumen Eksternal (memakai `DependentOptions` dan `ClassificationGuard`), Dokumen Karyawan (memakai `EmployeeDirectory`), Vendor, penghitungan pemakaian oleh dokumen (`Usage` + `master_data.usage_covers_documents`).

## Keputusan yang berlaku

- P1–P12 = rekomendasi `01-audit.md` §9; P8: admin PT dengan `settings.manage` mengelola platform (role pemegang: super_admin, admin, it_system).
- PT pengguna terkunci; super admin memilih lewat `tenant_id`; tidak ada akses lintas PT; tidak ada izin holding.
- Tanpa gaji dan NIK di master karyawan (tes menjaganya di tabel, profil, resource, dan payload HTTP). Karyawan = tabel sementara di balik `EmployeeDirectory`; baca hanya `employees.manage` (jangan lebarkan).
- Hubungan kerja hanya untuk pemegang `settings.manage` atau `settings.employment_types`; tidak ada perubahan izin apa pun.
- Tidak ada hapus permanen (daftar maupun karyawan); tidak ada route DELETE.
- Nilai bawaan dari kode Node, belum diverifikasi dengan produksi. Importer diuji hanya dengan fixture sintetis; tidak pernah dijalankan terhadap DB selain DB tes.
- Seed-defaults tidak punya endpoint HTTP (tidak ada izin yang jelas).
- Larangan git: hanya `status/diff/log/show`. Pengguna yang meng-commit. Jangan jalankan tinker yang menulis ke database dev.
- Jangan jalankan `npm run check:fix` atau `vp fmt` pada seluruh proyek (memformat ulang golden PDF dan laporan lama); format hanya berkas sendiri. Di F3 hal itu terjadi sekali dan dipulihkan dari `HEAD` (lihat 09 bagian 6).
- Teks, kolom, bantuan, urutan indeks, dan toast ada di `config/master_data.php`; Vue tidak boleh menulis daftar nilai bisnis.

## Pertanyaan terbuka (butuh keputusan pengguna)
1. Izin baca Audit Trail kelak (audit memuat nama karyawan) dan perlindungan `audit_logs` dari UPDATE/DELETE (B5).
2. Endpoint `seed-defaults` atau tetap artisan saja (F0).
3. Boleh HR/GA melihat daftar akun pengguna PT untuk menghubungkan akun ke karyawan? Sekarang tidak (F0).
4. `memory_limit` lebih besar di `composer test` dan `composer test:pg` (B2).
5. Tambahkan kontrak demo `GA-VND-2026-0001` ke daftar yang dikecualikan importer; uji pembaca terhadap salinan PostgreSQL Node (B6).
6. Abaikan `tests/Fixtures` dan `docs-tracked` pada formatter `vp` (F3).
7. Cek manual di ponsel nyata (daftar di `09-frontend-final.md` bagian 7).
8. Pemakaian oleh dokumen/kontrak belum dihitung (`usage_covers_documents=false`) sampai modul itu dimigrasi.

## Path berkas
Daftar lengkap ada di bagian "Berkas" tiap laporan (`02-b1-laporan` … `09-frontend-final`). Pekerjaan F1–F3 (belum di-commit): halaman `resources/js/pages/master-data/*` dan `errors/Forbidden.vue`; komponen `resources/js/components/master-data/**`; `resources/js/composables/{useMasterList,useReorder}.ts`; `resources/js/lib/masterData.ts`; `resources/js/types/master-data.ts`; tes `tests/js/master-data/*` dan `tests/Feature/Http/Controllers/MasterData/MasterListUiSupportTest.php`; perubahan `NavigationMenu.php`, `ListPage.php`, `MasterDataCatalog.php`, `config/master_data.php`, controller master data, dan `docs-tracked/master-data/{09-frontend-final,PROGRESS}.md`.
