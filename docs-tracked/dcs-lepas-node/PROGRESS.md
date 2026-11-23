# Lepas dari Node (DCS) — kemajuan

| Fase | Isi | Status |
|---|---|---|
| A | Audit baca-saja (`01-audit.md`) | Selesai 2026-11-23 |
| B | Buat revisi baru, lepas nomor di halaman dokumen | Selesai |
| C | Jadwal tinjau ulang, terjemah Inggris | Selesai |
| D | Tautan review eksternal dan halaman publik `/tinjau/dcs/{token}` | Selesai |
| Tes | Pemindahan tes kelompok C dan pemeriksaan kelompok B (`02-daftar-tes-api-lama.md`) | Selesai |
| E | Pensiun jembatan Node | Selesai 2026-11-23 |

## Catatan penutup Fase E

**Konteks.** Semua masih tahap development di mesin lokal; Laravel DCS belum di-deploy dan belum ada cutover. Tidak ada pemanggil `/api/dcs/*` di Laravel (dibuktikan dengan `rg` di seluruh repo, termasuk `resources/js` dan skrip), dan tidak ada proxy atau log produksi. Aplikasi Node tidak disentuh.

**Dihapus:** `routes/api.php` (47 rute DCS + 3 rute publik + 1 rute ekstrak metadata), seluruh `app/Http/Controllers/Dcs`, 10 FormRequest dan 9 Resource khusus API lama, middleware `dcs.auth` (`AuthenticateNodeSession`), `dcs.tenant` (`SetDcsTenant`) dan `CurrentTenant`, `NodeSessionToken`, `DcsErrorRenderer`, konfigurasi `node_jwt_secret`/`node_session_cookie`, `DCS_NODE_JWT_SECRET` di `.env.example`, 11 berkas tes controller API, 3 berkas tes jembatan, helper `dcsAs()`/`nodeJwt()`.

**Dipertahankan:** `DocTypeResource` dan `ClauseResource` (dipakai halaman konfigurasi), FormRequest yang dipakai halaman web (`App\Http\Requests\Dcs`), semua Action, `dcs:import-legacy`, `dcs:export-legacy`, koneksi `legacy`, kolom `legacy_id`, `tests/golden/capture.sh` (merekam API **Node**, bukan Laravel).

**Kode mati ikut dihapus** (tanpa pemanggil setelah API hilang): parameter nomor manual Terbitan/Revisi di `CreateVersion` (beserta pemeriksaan "sudah dipakai" yang tak lagi bisa tercapai dan tesnya), masukan `initialMajor`/`initialMinor` di `CreateDocument`, `ExtractMetadataController`.

**Tidak dihapus:** `DistributeVersion::handle()` (penerima berdasarkan id Node): tanpa pemanggil selain tes; calon dibersihkan bila ingin. Masukan `ownerUserId` di `CreateDocument` tetap karena `whereApiId` menerima id Laravel dan id Node.

**Risiko cutover (belum terjadi):** format token review Node yang sudah beredar hanya terbukti dari kode sumber (`randomBytes(24).toString("hex")`, 48 heksadesimal); rute `/tinjau/dcs/{token}` hanya menerima huruf dan angka. Notifikasi DCS masih `NullDcsNotifier`. Runbook cutover di `CLAUDE.md` belum pernah dilatih dengan data nyata.
