# 05 — Laporan Blok B4: karyawan minimal

Dibuat 2026-11-20. Tanpa UI, controller, atau route. B5 (audit) sebagian besar sudah terpenuhi di B2 dan B4; B6 (importer) belum dikerjakan.

## 1. Dikerjakan
- **`EmployeeDirectory`** (interface, `app/Support/MasterData/Employees`): satu-satunya pintu modul lain ke data karyawan. `options(Tenant)` (karyawan aktif, urut nama) dan `find(Tenant, id)` yang mengembalikan `EmployeeProfile` (nama, jabatan, nomor, departemen, hubungan kerja, tanggal mulai; **tanpa gaji dan NIK**). Implementasi `LocalEmployeeDirectory` memakai tabel lokal `employees` (sementara, bisa diganti sumber HR tanpa mengubah pemanggil) lewat `#[Bind]`.
- **`EmployeePolicy`**: hanya pemegang `employees.manage` (admin dan hr_ga secara bawaan) di PT-nya; PT lain 404; super admin ke semua.
- **`SaveEmployee`**: departemen (DCS), hubungan kerja, dan akun harus milik PT yang sama; satu akun hanya untuk satu karyawan; nomor karyawan unik per PT tanpa membedakan huruf besar/kecil. Audit lama/baru di transaksi yang sama.
- **`SetEmployeeActive`**: sembunyikan atau tampilkan lagi; tidak ada hapus. Diam bila tidak ada perubahan.
- **`SaveEmployeeRequest`**, **`EmployeeResource`** (daftar putih field, `can` dari Policy), **`EmployeeListSpec`** (cari nama/nomor/jabatan; filter status, departemen, hubungan kerja; urut nama/tanggal mulai/dibuat).
- CLAUDE.md diperbarui.

## 2. Bukti
- 22 tes baru, lulus: matriks role (admin dan hr_ga boleh; legal, it_system, manajemen, viewer 403), 404 lintas PT, referensi lintas PT ditolak (departemen, hubungan kerja, akun), satu akun satu karyawan, nomor unik per PT (nomor sama di PT lain boleh), audit lama/baru, sembunyikan/tampilkan tanpa menghapus, direktori hanya melihat PT sendiri dan karyawan aktif, form memberi pesan yang menyebut cara memperbaiki.
- **Penjaga data sensitif**: tes memeriksa bahwa kolom tabel, properti `EmployeeProfile`, dan kunci `EmployeeResource` tidak memuat `salary`, `gaji`, `nik`, `national_id`, `ktp`, `npwp`.
- Suite penuh: PostgreSQL 1404/1404 lulus; SQLite 1391 lulus, 13 dilewati (`memory_limit=512M`).
- Pint lulus. PHPStan: 0 error di berkas master data dan karyawan. Tidak ada berkas melebihi 200 baris (terbesar `SaveEmployeeRequest` 105 baris). Tidak ada skema baru, jadi tidak ada EXPLAIN baru; indeks tabel `employees` dari B1 sudah dibuktikan di laporan B1.

## 3. Beda dari Node dan dari dokumen
- Node: semua pengguna yang login membaca seluruh karyawan **beserta gaji**, dan `PUT` menyebar isi permintaan ke rekaman. Di sini hanya pemegang `employees.manage`, tanpa gaji, dengan daftar field tetap.
- Node tidak mengaudit karyawan; di sini setiap perubahan diaudit.
- Node menghapus permanen; di sini disembunyikan.
- Isi otomatis kontrak (Node `src/App.tsx:9257-9290`) mengisi `Salary` dan `contractValue = gaji × 12`. Itu tidak ada lagi; gaji harus diketik per kontrak (P2).

## 4. Konflik dokumen vs kode
- Tidak ada baru.

## 5. Risiko dan yang belum diverifikasi
- **Siapa yang boleh memilih karyawan untuk mengisi kontrak?** Direktori tidak memeriksa izin; pemanggil harus memeriksa `viewAny` pada `Employee`, artinya hanya `employees.manage`. Pengguna yang membuat kontrak karyawan tetapi tidak memegang izin itu (mis. legal dengan `contract.edit`) belum bisa memilih karyawan. Ini menyangkut akses data pribadi, jadi tidak saya putuskan (lihat pertanyaan 1).
- Audit log menyimpan nama, jabatan, dan nomor karyawan (data pribadi non-sensitif) sebagai nilai lama/baru. Perlu dipastikan itu boleh (pertanyaan 2).
- Belum ada controller/route; belum teruji lewat HTTP.
- Tidak ada pembaruan massal dan tidak ada impor dari Node (B6).
- `Usage` karyawan belum ada (kontrak belum dimigrasi), jadi `SetEmployeeActive` tidak melaporkan jumlah pemakaian.

## 6. Temuan di luar lingkup
- Tidak ada baru. Yang lama tetap: `composer test` dan `composer test:pg` perlu `memory_limit` lebih besar; ±105 error PHPStan lama di kode DCS.

## 7. Pertanyaan terbuka
1. **Siapa yang boleh membaca daftar karyawan untuk mengisi kontrak karyawan?** Pilihan: (a) hanya `employees.manage` (sekarang, paling aman), (b) juga pemegang `contract.edit`, hanya field `{id, nama, jabatan}`. Menyangkut data pribadi, jadi saya berhenti di (a). Rekomendasi: (b) dibatasi ke dokumen karyawan, tetapi keputusan Anda. [Keyakinan rendah]
2. **Boleh nama karyawan tercatat di audit log?** Sekarang ya (nilai lama/baru). Alternatif: catat hanya id dan nama kolom yang berubah. Rekomendasi: biarkan, karena audit karyawan tanpa nama sulit dibaca. [Keyakinan sedang]
3. **Hapus permanen karyawan yang salah ketik** (belum pernah dipakai kontrak)? Rekomendasi: tunda sampai ada kebijakan retensi data pribadi. [Keyakinan sedang]
