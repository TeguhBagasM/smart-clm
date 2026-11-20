<?php

/*
|--------------------------------------------------------------------------
| Data Vendor
|--------------------------------------------------------------------------
|
| Texts, columns, help and import settings of the vendor page (Node: Konfigurasi
| › Master Data › Data Vendor, src/App.tsx:17982-18005, 25691-25746). Vue shows
| what is here and writes no business text of its own. Audit and decisions:
| docs-tracked/vendor-template/01-audit.md (Q-V1…Q-V7).
|
*/

return [

    'label' => 'Vendor',
    'title' => 'Data vendor',
    'description' => 'Perusahaan atau perorangan yang menjadi pihak kedua di kontrak. Data rekening dan kontak hanya terlihat oleh pengelola vendor.',
    'search_placeholder' => 'Cari nama vendor atau NPWP…',
    'empty' => 'Belum ada vendor. Tambahkan yang pertama dengan tombol di atas.',
    'add' => 'Tambah vendor',

    'columns' => [
        ['key' => 'name', 'label' => 'Nama vendor'],
        ['key' => 'npwp', 'label' => 'NPWP'],
        ['key' => 'pic', 'label' => 'Kontak'],
        ['key' => 'bank', 'label' => 'Rekening'],
    ],

    // The form, in two parts: the vendor itself, then bank and tax (Node: "Info Rekening & Pajak").
    'sections' => [
        ['key' => 'main', 'title' => 'Data utama', 'fields' => ['name', 'npwp', 'address', 'pic_name', 'pic_phone', 'email']],
        ['key' => 'bank', 'title' => 'Rekening dan pajak', 'fields' => ['bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number', 'pph_rate_percent']],
    ],

    'fields' => [
        'name' => ['label' => 'Nama vendor', 'help' => 'Nama perusahaan atau orang, sama seperti di dokumen resminya.'],
        'npwp' => ['label' => 'NPWP', 'help' => '15 atau 16 angka. Titik dan tanda hubung boleh diketik atau tidak.', 'placeholder' => '00.000.000.0-000.000'],
        'address' => ['label' => 'Alamat', 'help' => 'Alamat yang dicetak di kontrak.'],
        'pic_name' => ['label' => 'Nama kontak', 'help' => 'Orang yang dihubungi di pihak vendor.'],
        'pic_phone' => ['label' => 'No. WhatsApp kontak', 'help' => 'Contoh: 081234567890 atau +6281234567890.'],
        'email' => ['label' => 'Email', 'help' => 'Alamat email vendor atau kontaknya.'],
        'bank_name' => ['label' => 'Bank', 'help' => 'Nama bank, misalnya BCA atau Bank Mandiri.'],
        'bank_branch' => ['label' => 'Cabang', 'help' => 'Cabang bank, bila perlu.'],
        'bank_account_name' => ['label' => 'Nama pemilik rekening', 'help' => 'Nama yang tertulis di buku rekening.'],
        'bank_account_number' => [
            'label' => 'Nomor rekening', 'help' => 'Hanya angka. Nomor disimpan terkunci dan tidak ditampilkan utuh lagi.',
            'keep_help' => 'Tersimpan: rekening berakhiran :tail. Kosongkan bila tidak diganti.', 'clear' => 'Hapus nomor rekening yang tersimpan',
        ],
        'pph_rate_percent' => ['label' => 'Potongan PPh (%)', 'help' => 'Persentase PPh yang dipotong dari pembayaran ke vendor ini, 0 sampai 100.'],
    ],

    // Search, filters and sorts of the list (VendorListSpec).
    'list' => [
        'filters' => [
            'status' => ['label' => 'Status', 'options' => ['active' => 'Aktif', 'inactive' => 'Disembunyikan']],
            'completeness' => ['label' => 'Kelengkapan', 'options' => ['no_npwp' => 'NPWP belum diisi', 'no_bank' => 'Rekening belum diisi']],
        ],
        'sorts' => ['name' => 'Nama', 'updated_at' => 'Terakhir diubah', 'created_at' => 'Tanggal dibuat'],
    ],

    // Shown instead of a blank cell.
    'blank' => ['npwp' => 'Belum diisi', 'pic' => 'Belum diisi', 'bank' => 'Belum diisi'],
    'bank_mask' => ':bank •••• :tail',

    'messages' => [
        'created' => 'Vendor ":name" ditambahkan.',
        'updated' => 'Vendor ":name" disimpan.',
        'deactivated' => 'Vendor ":name" disembunyikan dari pilihan kontrak baru.',
        'reactivated' => 'Vendor ":name" ditampilkan lagi di pilihan.',
        'impact_hide_title' => 'Sembunyikan vendor ":name"?',
        'impact_used' => 'Vendor ini dipakai di :usage kontrak. Kontrak itu tetap memakainya; yang berubah hanya pilihan untuk kontrak baru.',
        'impact_unused' => 'Vendor ini hanya hilang dari pilihan kontrak baru. Datanya tetap tersimpan dan bisa ditampilkan lagi kapan saja.',
        'impact_show_title' => 'Tampilkan vendor ":name" lagi?',
        'impact_show' => 'Vendor ini akan muncul lagi di pilihan kontrak baru.',
        'usage_notice' => 'Kontrak belum dipindah ke aplikasi ini, jadi pemakaian vendor oleh kontrak belum bisa dihitung.',
        'save_failed' => 'Belum bisa menyimpan. Isian Anda masih ada. Periksa sambungan internet atau muat ulang halaman, lalu coba lagi.',
        'duplicate_name' => 'Nama vendor ini sudah ada. Pakai nama lain, atau ubah vendor yang sudah ada.',
    ],

    // Whether usage counts contracts yet. It does not until contracts are migrated.
    'usage_covers_contracts' => false,

    // vendors:import-legacy reads the Node database through the master data import's
    // read-only connection (master_data.import.connection).
    'import' => [
        // Mock rows of the Node code (server.ts:1550-1554): never imported.
        'demo_ids' => ['VND001', 'VND002', 'VND003'],
    ],
];
