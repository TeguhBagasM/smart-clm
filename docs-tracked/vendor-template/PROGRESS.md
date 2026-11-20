# PROGRESS — Data Vendor dan Klausul & Template (Node → Laravel)

Diperbarui: akhir Fase C4 (saran AI untuk pasal). Sebelum melanjutkan di sesi baru, baca ulang instruksi tugas, `CLAUDE.md`, `docs-tracked/master-data/PROGRESS.md`, lalu `01-audit.md`, `02-b-data-vendor.md` dan `03-c1-skema.md` di folder ini.

## Selesai
- Fase A: audit → `01-audit.md`. Semua rekomendasi disetujui pengguna ("Setuju semua").
- Fase B (B1-B6): Data Vendor → `02-b-data-vendor.md`, `02-b1-explain.txt`.
- Fase C1: 7 tabel, enum, model, penjaga versi beku (trigger PostgreSQL + event model), nilai bawaan + seeder → `03-c1-skema.md`, `03-c1-explain.txt`.
- Fase C2: 9 Action, 3 Policy, 3 ListSpec, 4 Resource, 7 Form Request (tanpa controller/route/UI) → `04-c2-library.md`. Suite: PostgreSQL 1651/1651, SQLite 1637 + 14 dilewati.
- Fase C3: parser placeholder, penjaga (pasal dan draf), renderer anti-injeksi, pratinjau dengan contoh → `05-c3-mesin-variabel.md`. Suite: PostgreSQL 1692/1692, SQLite 1678 + 14 dilewati.
- Fase C4: saran pasal dengan AI (tanpa menyimpan, izin `templates.manage`, prompt tanpa data PT, keluaran divalidasi, audit tanpa isi) → `06-c4-saran-ai.md`. Suite: PostgreSQL 1704/1704, SQLite 1690 + 14 dilewati.

## Berikutnya
- **BERHENTI** setelah C4: menunggu "lanjut" untuk C5 (impor dari Node) dan jawaban `06-c4-saran-ai.md` §7.
- Lalu C5 (impor dari Node), C6 (controller, route, tes HTTP; berhenti wajib setelah C6).

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
- Dari `04-c2-library.md` §7: pasal terlindungi cukup `templates.manage` atau perlu batasan tambahan.
- Dari `03-c1-skema.md` §7 (sudah disetujui): tinggal migrasi dan isi DB dev saat C6.
- Lama, dari `03-c1-skema.md` §7: pengganti `current_version_id`, `legacy_key` sebagai kunci bawaan, kolom isian `Salary`, migrasi/seed DB dev.

## Path berkas
- Dokumen: `docs-tracked/vendor-template/{01-audit.md,02-b-data-vendor.md,02-b1-explain.txt,03-c1-skema.md,03-c1-explain.txt,PROGRESS.md}`.
- Fase B: daftar lengkap di `02-b-data-vendor.md` bagian "Berkas".
- Fase C1: daftar lengkap di `03-c1-skema.md` bagian "Berkas".
- Fase C2: daftar lengkap di `04-c2-library.md` bagian "Berkas".
- Fase C3: daftar lengkap di `05-c3-mesin-variabel.md` bagian "Berkas".
- Fase C4: daftar lengkap di `06-c4-saran-ai.md` bagian "Berkas".
