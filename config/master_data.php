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

    // Names shown to people for each list (singular). Keyed by App\Enums\MasterData\MasterList::value.
    'lists' => [
        'platforms' => 'Platform',
        'categories' => 'Kategori dokumen eksternal',
        'document-types' => 'Jenis surat dan perjanjian',
        'employment-types' => 'Status hubungan kerja',
        'party-types' => 'Jenis mitra kontrak',
        'currencies' => 'Mata uang',
        'partner-groups' => 'Kelompok mitra',
        'partners' => 'Mitra',
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
