# 04 — Fase C2: Policy, Form Request, Resource, ListSpec, Action (Klausul & Template)

Tanggal: 2026-11-20. Persetujuan: "Setuju, lanjut C2" atas empat rekomendasi `03-c1-skema.md` §7 (indeks satu versi terbit menggantikan `current_version_id`; `legacy_key` sebagai kunci nilai bawaan; kolom isian `Salary` tetap sebagai definisi; migrasi dan isi DB dev saat C6). Sesuai instruksi, C2 hanya lapisan aplikasi: **tanpa controller, route, dan UI** (itu C6, dan berhenti setelahnya).

## 1. Dikerjakan

**Action** (`app/Actions/Templates`), semuanya transaksional, menulis audit dalam transaksi yang sama, dan memeriksa perusahaan:

| Action | Yang dilakukan | Aturan yang dijaga |
|---|---|---|
| `SaveContractClause` | buat/ubah pasal | teks biasa (HTML ditolak), judul unik per PT, kategori milik PT; `revision` naik hanya bila isi berubah |
| `SaveContractVariable` | buat/ubah kolom isian | kode `^[A-Za-z][A-Za-z0-9_]*$`, nama dan kode unik per PT, sumber otomatis harus dari katalog; **kode tidak bisa diganti** bila dipakai versi, pasal, atau salinan pasal (`{{Kode}}` dicari di isi) |
| `SaveContractTemplate` | buat/ubah identitas template | buat sekaligus **draf versi 1**; jenis (eksternal/karyawan) mengikuti kategori; ubah nama/deskripsi tidak menyentuh versi |
| `SaveTemplateDraft` | simpan seluruh draf (3 paragraf, pihak, pasal dari perpustakaan, kolom isian) | hanya draf; `expected_revision` harus cocok (kunci optimistik); pasal disalin saat disimpan; pasal/kolom nonaktif atau milik PT lain ditolak; tidak ada duplikat; batas jumlah |
| `ReorderTemplateClauses` | urutkan pasal draf | daftar harus lengkap; dua langkah (geser lalu tulis) agar indeks unik posisi tidak bentrok |
| `PublishTemplateVersion` | terbitkan draf | wajib ≥1 pasal dan ≥1 pihak; versi terbit lama **dipensiunkan lebih dulu** dalam transaksi yang sama |
| `StartTemplateDraft` | buat draf baru dari versi terbit (nomor berikutnya) | satu draf per template; versi terbit tidak pernah diedit langsung |
| `DuplicateContractTemplate` | salin template ke nama baru (`X (salinan)`, `X (salinan 2)`) | isi versi terbit (atau terakhir) menjadi draf 1 salinan; sumber tidak disentuh |
| `SetTemplateRecordActive` | sembunyikan/tampilkan pasal, kolom isian, atau template | tidak menghapus; mengembalikan jumlah pemakaian; idempoten |

Pendukung (`app/Support/Templates`): `PlainText` (tolak tag HTML dan batas panjang), `UniqueLibraryName` (nama unik tanpa beda huruf, dengan pesan), `DraftVersions::lockedDraft` (kunci baris + cek revisi + pesan "tidak ada draf"), `CopiesTemplateVersion` (salin isi versi). Antarmuka `CountsUsage` diimplementasikan oleh pasal, kolom isian, dan template.

**Policy** (`app/Policies/Templates`): `ContractClausePolicy`, `ContractVariablePolicy`, `ContractTemplatePolicy`; semuanya `templates.manage` di PT sendiri lewat `MasterDataPolicy` (PT lain 404, izin kurang 403). Template punya kemampuan tambahan `saveDraft`, `publish`, `startDraft`, `duplicate` yang semuanya memakai izin yang sama.

**ListSpec** (`app/Support/Listing`): `ContractClauseListSpec` (cari judul/isi; filter status, kategori, jenis wajib/terlindungi; urut judul/terakhir diubah), `ContractVariableListSpec` (cari kode/nama; filter status, jenis; urut nama/kode), `ContractTemplateListSpec` (cari nama/deskripsi; filter status, jenis dokumen, kategori, terbit atau belum). Semua label dari `config/contract_templates.php` (`list`).

**Resource** (`app/Http/Resources/Templates`): `ContractClauseResource`, `ContractVariableResource`, `ContractTemplateResource` (dengan `can` per kemampuan, termasuk `startDraft` hanya bila belum ada draf), `TemplateVersionResource` (pasal dengan `source_updated` bila pasal perpustakaan sudah berubah sejak disalin).

**Form Request** (`app/Http/Requests/Templates`): basis `LibraryFormRequest` (catatan rute `record`, perusahaan dari pengguna atau `tenant_id` untuk super admin, `authorize` lewat Policy) dan enam turunan: `SaveContractClauseRequest`, `SaveContractVariableRequest`, `SaveContractTemplateRequest`, `SaveTemplateDraftRequest`, `ReorderTemplateClausesRequest`, `PublishTemplateVersionRequest`. Setiap turunan menyediakan data bertipe untuk Action (`clauseData()`, `variableData()`, `templateData()`, `draftData()`, `orderData()`, `expectedRevision()`).

**Konfigurasi** (`config/contract_templates.php`): `list` (label), `limits` (batas teks dan jumlah per versi), `messages` (semua teks galat dan nama salinan dalam bahasa Indonesia).

## 2. Bukti

- Tes baru (40 tes termasuk C1; 39 lulus di SQLite, 1 dilewati di SQLite yang khusus PostgreSQL):
  - `tests/Feature/Policies/TemplateLibraryPolicyTest.php`: per peran (admin dan legal lulus; hr_ga, finance, it_system, viewer ditolak 403); PT lain 404; super admin di semua PT.
  - `tests/Feature/Actions/Templates/SaveLibraryTest.php` (10): revisi naik hanya bila isi berubah dan audit dicatat; HTML dan judul ganda ditolak; kategori PT lain ditolak, pasal PT lain 404; kode tak bisa diganti bila dipakai pasal atau versi; template baru punya draf 1 dan jenis mengikuti kategori; ubah nama tidak menyentuh versi; sembunyikan/tampilkan dengan jumlah pemakaian dan audit.
  - `tests/Feature/Actions/Templates/TemplateDraftTest.php` (7): draf tersimpan dengan salinan pasal (revisi sumber), pihak, dan kolom isian; revisi usang ditolak; pasal tersembunyi, PT lain, atau ganda ditolak; urutan berubah dan urutan tidak lengkap ditolak; terbit butuh pasal dan pihak, terbit kedua mempensiunkan yang pertama dan draf setelahnya tidak bisa disimpan; draf baru dari versi terbit bernomor berikutnya, hanya satu; duplikat dengan nama bernomor.
  - `tests/Feature/Templates/LibraryListsAndResourcesTest.php` (3): cari, filter, urut, dan isolasi PT pada daftar pasal; filter terbit/belum terbit pada template; `can` per peran dan `source_updated` pada pasal salinan.
- Suite penuh (`php -d memory_limit=512M`): **PostgreSQL 1651/1651**; **SQLite 1637 lulus, 14 dilewati** (tes khusus PostgreSQL). 0 gagal.
- Pint: merapikan impor; PHPStan bersih untuk semua berkas C2. Sisa temuan di `app/Support/Listing/LookupListSpec.php` (baris 39, 42) sudah ada sebelumnya dan tidak saya ubah.
- Ukuran berkas: 28 berkas C2 baru/berubah, terbesar `SeedTenantTemplateLibrary.php` 180 baris (dari C1), `SaveTemplateDraft.php` 161; tidak ada yang > 200.
- Tidak ada perubahan skema di C2 (migrasi C1 tetap).

## 3. Beda dari Node dan dari dokumen

- Node mengubah redaksi template di tempat dan menaikkan nomor versi; di sini versi terbit beku, dan perubahan membuat draf baru (Q-T2).
- Node menghapus pasal dan template secara permanen; di sini hanya disembunyikan (`SetTemplateRecordActive`).
- Node menyimpan HTML pasal; di sini teks biasa, HTML ditolak di server (Q-T1).
- Node menerima kunci kolom isian bebas; di sini kode dicek format dan dikunci bila sudah dipakai.
- Pasal terlindungi: Node membatasi ke admin, legal, dan super admin; di sini pemegang `templates.manage` (sama dengan default: admin, legal, super admin). Belum ada aturan tambahan khusus pasal terlindungi.

## 4. Konflik dokumen vs kode

Tidak ada baru selain yang sudah tercatat di C1 (`current_version_id` diganti indeks; `legacy_key` sebagai kunci bawaan).

## 5. Risiko dan yang belum diverifikasi

- **Pemeriksaan placeholder belum ada.** Isi pasal bisa memuat `{{Kode}}` yang tidak ada di daftar kolom isian; validasinya dan pratinjau aman dari injeksi adalah **C3**. Sampai C3, kolom isian dan pasal bisa tidak sinkron.
- **Form Request belum diuji langsung.** Aturan validasi (format, batas, `Rule::enum`) dan respons 403/404/422 lewat HTTP diuji di C6 bersama controller dan route.
- **Penghitung pemakaian di daftar** perlu `withCount` dari controller (C6). Sekarang `usage_count` jatuh ke 0 bila tidak dimuat.
- **Pivot kolom isian tidak dijaga event model**: `attach`/`detach` lewat Action sudah memeriksa draf di bawah kunci; penjaga PostgreSQL tetap berlaku.
- **Yang belum dibuat:** controller, route, halaman, impor dari Node (C5), saran AI (C4), dan pemeriksaan browser.
- DB dev belum dimigrasi untuk tabel C1; C6 akan melakukannya.

## 6. Temuan di luar lingkup

- `LookupListSpec` (Daftar Pilihan) punya dua temuan PHPStan lama (`whereRaw` dengan nama kolom dinamis).

## 7. Pertanyaan terbuka

1. Pasal terlindungi: cukup dengan `templates.manage`, atau perlu batasan tambahan seperti Node (admin dan legal saja)? Rekomendasi: cukup `templates.manage` karena default-nya sama; keputusan izin tetap di Anda. [Keyakinan sedang]
2. Lanjut ke C3 (mesin variabel: validasi placeholder, pratinjau dengan data contoh, uji input berbahaya)? Rekomendasi: ya. [Keyakinan tinggi]

## Berkas

Dibuat: `app/Actions/Templates/{SaveContractClause,SaveContractVariable,SaveContractTemplate,SetTemplateRecordActive,SaveTemplateDraft,ReorderTemplateClauses,PublishTemplateVersion,StartTemplateDraft,DuplicateContractTemplate}.php`, `app/Support/Templates/{PlainText,UniqueLibraryName,DraftVersions,CopiesTemplateVersion}.php`, `app/Models/Templates/CountsUsage.php`, `app/Policies/Templates/{ContractClausePolicy,ContractVariablePolicy,ContractTemplatePolicy}.php`, `app/Support/Listing/{ContractClauseListSpec,ContractVariableListSpec,ContractTemplateListSpec}.php`, `app/Http/Resources/Templates/{ContractClauseResource,ContractVariableResource,ContractTemplateResource,TemplateVersionResource}.php`, `app/Http/Requests/Templates/{LibraryFormRequest,SaveContractClauseRequest,SaveContractVariableRequest,SaveContractTemplateRequest,SaveTemplateDraftRequest,ReorderTemplateClausesRequest,PublishTemplateVersionRequest}.php`, `tests/Feature/Policies/TemplateLibraryPolicyTest.php`, `tests/Feature/Actions/Templates/{SaveLibraryTest,TemplateDraftTest}.php`, `tests/Feature/Templates/LibraryListsAndResourcesTest.php`, `docs-tracked/vendor-template/04-c2-library.md`.

Diubah: `app/Models/Templates/{ContractClause,ContractVariable,ContractTemplate}.php` (interface dan `usageCount()`), `config/contract_templates.php` (`list`, `limits`, `messages`), `docs-tracked/vendor-template/PROGRESS.md`.
