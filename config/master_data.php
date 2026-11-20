<?php

/*
|--------------------------------------------------------------------------
| Master data defaults
|--------------------------------------------------------------------------
|
| What a company receives from `php artisan master-data:seed-defaults` (and
| the database seeder). Each company gets its own copy, which it may rename,
| reorder or deactivate; the command only adds what is missing and never
| changes or re-adds a row the company already has, active or not.
|
| Every value below is copied from the Node app (management-contract/) at the
| path:line noted. NOT YET VERIFIED AGAINST PRODUCTION DATA: the Node database
| was not available, so these are the code defaults, not what companies use.
|
| Lists without codes in Node get a code derived from the name
| (App\Support\MasterData\LookupCode). Deliberately not seeded:
| - Node docType "Lainnya" (server.ts:1895): no "other, type by hand" choice (audit P11).
| - Platforms (server.ts:1597-1601, 7692-7695) and partner groups
|   (operatorDefaults.ts): they name specific companies, so they come from the importer only.
| - Employees and vendors: Node's are demo rows (server.ts:1544-1554).
|
*/

return [

    // Shown to people, one entry per list (singular `label`). Keyed by App\Enums\MasterData\MasterList::value,
    // plus `employees`. `columns` are what a list page shows (key = field of the Resource), `help` is the short
    // text under a form field. Nothing here is written in the Vue files.
    'lists' => [
        'platforms' => [
            'label' => 'Platform', 'description' => 'Layanan atau produk perusahaan tempat kerja sama dijalankan, misalnya Tiketux.',
            'columns' => [['key' => 'name', 'label' => 'Nama'], ['key' => 'code', 'label' => 'Kode']],
            'help' => ['name' => 'Nama platform seperti yang dikenal tim.', 'code' => 'Dipakai di nomor dokumen. Kosongkan agar dibuat otomatis dari nama.'],
        ],
        'categories' => [
            'label' => 'Kategori dokumen eksternal', 'description' => 'Pengelompokan dokumen kerja sama dengan pihak luar, misalnya Vendor atau NDA.',
            'columns' => [['key' => 'name', 'label' => 'Nama'], ['key' => 'code', 'label' => 'Kode']],
            'help' => ['name' => 'Nama kategori yang muncul saat membuat dokumen.', 'code' => 'Kosongkan agar dibuat otomatis dari nama.'],
        ],
        'document-types' => [
            'label' => 'Jenis surat dan perjanjian', 'description' => 'Jenis dokumen yang bisa dibuat, misalnya Perjanjian Kerjasama atau Surat Peringatan.',
            'columns' => [['key' => 'name', 'label' => 'Nama'], ['key' => 'code', 'label' => 'Kode'], ['key' => 'module', 'label' => 'Dipakai di']],
            'empty' => ['categories' => 'Semua kategori'],
            'help' => [
                'name' => 'Nama jenis dokumen.', 'code' => 'Singkatan untuk nomor dokumen, mis. PKS. Tidak bisa diubah pada jenis bawaan.',
                'module' => 'Pilih untuk dokumen eksternal, dokumen karyawan, atau keduanya.',
                'categories' => 'Centang kategori yang cocok. Tanpa centang, jenis ini tersedia di semua kategori.',
                'retention_years' => 'Berapa tahun dokumen disimpan sejak tidak berlaku. Kosongkan bila belum ditetapkan.',
                'extra_codes' => 'Kode tambahan yang ikut dicetak di nomor dokumen, misalnya kode divisi.',
            ],
        ],
        'employment-types' => [
            'label' => 'Status hubungan kerja', 'description' => 'Status karyawan pada dokumen karyawan, misalnya PKWT atau Magang.',
            'columns' => [['key' => 'name', 'label' => 'Nama'], ['key' => 'code', 'label' => 'Kode']],
            'help' => ['name' => 'Nama status hubungan kerja.', 'code' => 'Kosongkan agar dibuat otomatis dari nama.'],
        ],
        'party-types' => [
            'label' => 'Jenis mitra kontrak', 'description' => 'Jenis pihak kedua pada kontrak, misalnya Vendor atau Customer.',
            'columns' => [['key' => 'name', 'label' => 'Nama'], ['key' => 'code', 'label' => 'Kode']],
            'help' => ['name' => 'Nama jenis pihak kedua.', 'code' => 'Kosongkan agar dibuat otomatis dari nama.'],
        ],
        'currencies' => [
            'label' => 'Mata uang', 'description' => 'Mata uang yang bisa dipilih pada kontrak.',
            'columns' => [['key' => 'code', 'label' => 'Kode'], ['key' => 'name', 'label' => 'Nama']],
            'help' => ['name' => 'Nama mata uang, misalnya Rupiah.', 'code' => 'Tiga huruf, misalnya IDR atau USD.'],
        ],
        'partner-groups' => [
            'label' => 'Kelompok mitra', 'description' => 'Pengelompokan mitra, bisa dibatasi pada platform tertentu.',
            'columns' => [['key' => 'name', 'label' => 'Nama'], ['key' => 'platforms', 'label' => 'Platform']],
            'empty' => ['platforms' => 'Semua platform'],
            'help' => ['name' => 'Nama kelompok mitra.', 'platform_ids' => 'Centang platform yang memakai kelompok ini. Tanpa centang, kelompok berlaku di semua platform.'],
        ],
        'partners' => [
            'label' => 'Mitra', 'description' => 'Perusahaan rekanan yang bisa dipilih pada dokumen eksternal.',
            'columns' => [['key' => 'name', 'label' => 'Nama'], ['key' => 'group', 'label' => 'Kelompok']],
            'help' => ['name' => 'Nama perusahaan mitra.', 'partner_group_id' => 'Pilih kelompok tempat mitra ini berada.'],
        ],
        'employees' => [
            'label' => 'Karyawan', 'description' => 'Daftar karyawan untuk mengisi dokumen karyawan. Tanpa gaji dan tanpa NIK.',
            'columns' => [['key' => 'name', 'label' => 'Nama'], ['key' => 'position', 'label' => 'Jabatan'], ['key' => 'department', 'label' => 'Departemen'], ['key' => 'employment_type', 'label' => 'Hubungan kerja']],
            'help' => [
                'name' => 'Nama lengkap karyawan.', 'employee_number' => 'Nomor induk karyawan, bila ada. Tidak boleh sama dengan karyawan lain.',
                'department_id' => 'Pilih dari daftar departemen perusahaan.', 'employment_type_id' => 'Pilih status hubungan kerja.',
                'start_date' => 'Tanggal mulai bekerja, bentuk tahun-bulan-tanggal.', 'user_id' => 'Hubungkan dengan akun pengguna bila karyawan ini punya akun.',
            ],
        ],
    ],

    // The index page: groups in this order, each with the lists (and `employees`) it shows.
    'groups' => [
        ['key' => 'dokumen', 'title' => 'Dokumen dan kontrak', 'description' => 'Pilihan yang muncul saat membuat dokumen.', 'lists' => ['categories', 'document-types', 'party-types', 'currencies']],
        ['key' => 'mitra', 'title' => 'Platform dan mitra', 'description' => 'Platform kerja sama dan rekanan di setiap platform.', 'lists' => ['platforms', 'partner-groups', 'partners']],
        ['key' => 'karyawan', 'title' => 'Karyawan', 'description' => 'Data untuk dokumen karyawan.', 'lists' => ['employment-types', 'employees']],
    ],

    // Longest list the order of which can be set in one go (the screen sends the whole list).
    'reorder_max' => 200,

    // Whether `usage_count` already includes documents and contracts. It does not until they are migrated.
    'usage_covers_documents' => false,

    'messages' => [
        'created' => ':label ditambahkan.',
        'updated' => ':label disimpan.',
        'reordered' => 'Urutan :label disimpan.',
        'reactivated' => ':label ditampilkan lagi di pilihan.',
        'deactivated' => ':label disembunyikan dari pilihan baru.',
        'deactivated_used' => ':label disembunyikan dari pilihan baru. Masih dipakai oleh :usage data lain, dan data itu tetap memakainya.',
        'usage_notice' => 'Jumlah pemakaian baru menghitung data master. Dokumen dan kontrak belum ikut dihitung.',
        'choose_company' => 'Pilih perusahaan terlebih dahulu.',
        'impact_hide_title' => 'Sembunyikan ":name"?',
        'impact_used' => 'Masih dipakai oleh :usage data lain. Data itu tetap memakainya; yang berubah hanya pilihan untuk data baru.',
        'impact_unused' => 'Belum ada data master lain yang memakainya. Pilihan ini hanya hilang dari daftar pilihan baru dan bisa ditampilkan lagi kapan saja.',
        'impact_show_title' => 'Tampilkan ":name" lagi?',
        'impact_show' => 'Pilihan ini akan muncul lagi di daftar pilihan.',
        'no_access' => 'Anda belum punya akses ke daftar master data mana pun. Hubungi admin perusahaan Anda bila perlu.',
        'forbidden_title' => 'Anda tidak punya akses ke halaman ini',
        'forbidden' => 'Halaman ini hanya untuk peran tertentu. Hubungi admin perusahaan Anda bila Anda perlu mengelolanya.',
        'reorder_too_many' => 'Daftar ini terlalu panjang untuk diurutkan sekaligus.',
        'reorder_pick_group' => 'Pilih satu kelompok pada filter dulu, lalu atur urutan mitra di dalamnya.',
        'reorder_failed' => 'Urutan gagal disimpan, jadi urutan semula dikembalikan. Coba lagi.',
        'save_failed' => 'Belum bisa menyimpan. Isian Anda masih ada. Periksa sambungan internet atau muat ulang halaman, lalu coba lagi.',
    ],

    // Reading the Node database (master-data:import-legacy). The connection is
    // the same read-only `legacy` one the DCS importer uses.
    'import' => [
        'connection' => env('MASTER_DATA_LEGACY_CONNECTION', 'legacy'),
        // Demo rows seeded by the Node code (server.ts:1544-1548, 1790-1800): never imported, never counted.
        'demo_employee_ids' => ['EMP001', 'EMP002', 'EMP003'],
        'demo_contract_numbers' => ['HR-PKWT-2026-0002'],
        // A Node entry named like this is the "other, type it in" choice: imported hidden, and reported.
        'other_pattern' => '/^lain[\s-]?lain(nya)?$|^lainnya$/i',
        // Fields of a Node contract compared with the lists, to report values missing from them.
        'contract_fields' => ['category', 'docType', 'platform', 'operator', 'employmentType', 'division', 'party2Type', 'currency'],
    ],

    'defaults' => [

        // A new Node company got these eight (server.ts:1273), then the external seed (server.ts:7680-7683).
        'contract_categories' => [
            'General', 'Vendor', 'Employment', 'Customer', 'Legalitas Perusahaan', 'NDA', 'MOU', 'Rental',
            'PKS (Perjanjian Kerjasama)', 'Perjanjian Layanan', 'Perjanjian Integrasi Sistem',
            'Perjanjian Vendor/Pengadaan', 'Perjanjian Kerahasiaan', 'Perjanjian Sewa', 'Perjanjian Promosi',
        ],

        // Node's literal key of employee contracts (src/App.tsx:6488-6493, src/types.ts:569).
        'employee_category' => 'Employment',

        // code, name, module (external|employee|both), categories (names above).
        // server.ts:1886-1894 (module from seedEmployeeTaxonomy, server.ts:7715-7738),
        // then server.ts:7684-7691 minus those whose name or code already exists (server.ts:7765-7768),
        // then server.ts:7697-7710.
        'contract_document_types' => [
            ['code' => 'PKS', 'name' => 'Perjanjian Kerjasama', 'module' => 'external', 'categories' => []],
            ['code' => 'PK', 'name' => 'Perjanjian Kerja (PKWT/PKWTT)', 'module' => 'employee', 'categories' => ['Employment']],
            ['code' => 'NDA', 'name' => 'NDA / Kerahasiaan', 'module' => 'external', 'categories' => []],
            ['code' => 'MOU', 'name' => 'MOU / Nota Kesepahaman', 'module' => 'external', 'categories' => []],
            ['code' => 'SWM', 'name' => 'Sewa Menyewa', 'module' => 'external', 'categories' => []],
            ['code' => 'ADD', 'name' => 'Addendum / Amandemen', 'module' => 'external', 'categories' => []],
            ['code' => 'LGL', 'name' => 'Legalitas / Izin Perusahaan', 'module' => 'external', 'categories' => ['Legalitas Perusahaan']],
            ['code' => 'DAK', 'name' => 'DAK', 'module' => 'external', 'categories' => []],
            ['code' => 'CI', 'name' => 'CI Surat Konfirmasi', 'module' => 'external', 'categories' => []],
            ['code' => 'SPN', 'name' => 'Surat Penawaran', 'module' => 'external', 'categories' => []],
            ['code' => 'SPY', 'name' => 'Surat Pernyataan', 'module' => 'external', 'categories' => []],
            ['code' => 'SPB', 'name' => 'Surat Pemberitahuan', 'module' => 'external', 'categories' => []],
            ['code' => 'STG', 'name' => 'Surat Teguran', 'module' => 'external', 'categories' => []],
            ['code' => 'SPP', 'name' => 'Surat Pengakhiran/Pemutusan', 'module' => 'external', 'categories' => []],
            ['code' => 'BA', 'name' => 'Berita Acara', 'module' => 'external', 'categories' => []],
            ['code' => 'OL', 'name' => 'Surat Penawaran Kerja (Offering Letter)', 'module' => 'employee', 'categories' => []],
            ['code' => 'PMG', 'name' => 'Perjanjian Magang', 'module' => 'employee', 'categories' => []],
            ['code' => 'NDK', 'name' => 'NDA / Perjanjian Kerahasiaan Karyawan', 'module' => 'employee', 'categories' => []],
            ['code' => 'PID', 'name' => 'Perjanjian Ikatan Dinas', 'module' => 'employee', 'categories' => []],
            ['code' => 'APK', 'name' => 'Addendum / Perpanjangan PKWT', 'module' => 'employee', 'categories' => []],
            ['code' => 'SPKT', 'name' => 'Surat Pengangkatan Karyawan Tetap', 'module' => 'employee', 'categories' => []],
            ['code' => 'SKM', 'name' => 'SK Mutasi / Promosi / Rotasi', 'module' => 'employee', 'categories' => []],
            ['code' => 'SP', 'name' => 'Surat Peringatan (SP)', 'module' => 'employee', 'categories' => []],
            ['code' => 'SPD', 'name' => 'Surat Pengunduran Diri', 'module' => 'employee', 'categories' => []],
            ['code' => 'PHK', 'name' => 'Surat PHK', 'module' => 'employee', 'categories' => []],
            ['code' => 'PB', 'name' => 'Perjanjian Bersama (PB)', 'module' => 'employee', 'categories' => []],
            ['code' => 'SKK', 'name' => 'Surat Keterangan Kerja (Paklaring)', 'module' => 'employee', 'categories' => []],
        ],

        // server.ts:1920
        'employment_types' => ['PKWT', 'PKWTT', 'Magang / Internship', 'Harian Lepas', 'Freelance / Tenaga Ahli Lepas'],

        // server.ts:1897
        'party_types' => ['Vendor', 'Karyawan', 'Customer / Klien', 'Mitra / Partner'],

        // Node's literal employee party type (src/App.tsx:6460).
        'employee_party_type' => 'Karyawan',

        // server.ts:1898 (codes only in Node; the name starts equal to the code).
        'currencies' => ['IDR', 'USD', 'SGD', 'EUR'],

    ],

];
