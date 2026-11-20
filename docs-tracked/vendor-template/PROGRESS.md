# PROGRESS — Data Vendor dan Klausul & Template (Node → Laravel)

Diperbarui: akhir Fase C1 (skema Klausul & Template). Sebelum melanjutkan di sesi baru, baca ulang instruksi tugas, `CLAUDE.md`, `docs-tracked/master-data/PROGRESS.md`, lalu `01-audit.md`, `02-b-data-vendor.md` dan `03-c1-skema.md` di folder ini.

## Selesai
- Fase A: audit → `01-audit.md`. Semua rekomendasi disetujui pengguna ("Setuju semua").
- Fase B (B1-B6): Data Vendor → `02-b-data-vendor.md`, `02-b1-explain.txt`.
- Fase C1: 7 tabel, enum, model, penjaga versi beku (trigger PostgreSQL + event model), nilai bawaan + seeder → `03-c1-skema.md`, `03-c1-explain.txt`.

## Berikutnya
- **BERHENTI**: menunggu "lanjut" dan jawaban pertanyaan `03-c1-skema.md` §7.
- Lalu C2 (Policy, Request, Resource, ListSpec, Action simpan/terbitkan/nonaktifkan/urutkan/duplikasi, audit) … C6, berhenti wajib setelah C6.

## Keputusan yang berlaku
- Semua keputusan `docs-tracked/master-data/PROGRESS.md` tetap berlaku (tanpa hapus permanen, nilai bawaan belum diverifikasi, larangan git, format hanya berkas sendiri).
- Q-V1…Q-V7 dan Q-T1…Q-T9 = rekomendasi di `01-audit.md`. Ringkas: vendor tabel sendiri, halaman hanya `vendors.manage`, rekening terenkripsi dan hanya-tulis, nama vendor unik per PT, tanpa CSV; isi pasal teks terstruktur tanpa HTML, versi template beku dengan salinan pasal, library hanya `templates.manage`, kolom isian per PT dipilih dari daftar, narasi per kategori ditunda, tag pasal dibuang, AI hanya "tulis draf pasal" tanpa data pribadi, template default per jenis ditunda, template bawaan diseed sebagai draf.
- Audit vendor tidak menyimpan nilai kolom sensitif (`changed_sensitive` hanya nama kolom).
- Importer vendor menolak impor sungguhan bila dry run tidak bersih (tanpa `--force`).
- Klausul DCS (`dcs_clauses`) tidak dipakai ulang dan tidak diubah.
- Versi template terbit beku (trigger PostgreSQL + event model); versi terbit = indeks unik parsial, tanpa `current_version_id` (usul, menunggu konfirmasi).
- Nilai bawaan Klausul & Template di `config/contract_templates.php`, `legacy_key` = id Node sebagai kunci tetap (usul, menunggu konfirmasi). Perintah: `contract-templates:seed-defaults`.
- Action yang menulis isi versi wajib mengunci baris versi dan memeriksa status draf (pivot kolom isian tidak dijaga event model).

## Pertanyaan terbuka
- Dari `02-b-data-vendor.md` §7: vendor uji di dev DB; rekening di salinan vendor pada kontrak kelak.
- Dari `03-c1-skema.md` §7: pengganti `current_version_id`, `legacy_key` sebagai kunci bawaan, kolom isian `Salary`, migrasi/seed DB dev.

## Path berkas
- Dokumen: `docs-tracked/vendor-template/{01-audit.md,02-b-data-vendor.md,02-b1-explain.txt,03-c1-skema.md,03-c1-explain.txt,PROGRESS.md}`.
- Fase B: daftar lengkap di `02-b-data-vendor.md` bagian "Berkas".
- Fase C1: daftar lengkap di `03-c1-skema.md` bagian "Berkas".
