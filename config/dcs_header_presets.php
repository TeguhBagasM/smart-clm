<?php

/*
 * Letterhead (kop) templates offered when a document type is set up. Plain data: a layout follows
 * App\Support\Dcs\Header\HeaderLayout (rules and normalisation live there). `default` is the key of the
 * template used by every document type that has no letterhead of its own. Colours are hex values the
 * letterhead validation accepts (the palette itself is HeaderPalette).
 */

return [
    'default' => 'sop',
    'presets' => [

        [
            'key' => 'sop',
            'name' => 'Kop SOP Indonesia',
            'description' => 'Logo di kiri, nama perusahaan, jenis dan judul, lalu nomor, terbit ke, revisi ke, tanggal efektif, dan halaman.',
            'layout' => [
                'version' => 2,
                'border' => '#404552',
                'frame' => true,
                'logo' => [
                    'enabled' => true,
                    'source' => 'company',
                    'assetId' => null,
                    'side' => 'left',
                    'rows' => 5,
                    'width' => 21,
                    'valign' => 'middle',
                    'padding' => 8,
                    'border' => true,
                ],
                'rows' => [

                    [
                        'height' => 26,
                        'cells' => [

                            [
                                'width' => 100.0,
                                'text' => '{companyName}',
                                'bold' => true,
                                'italic' => false,
                                'uppercase' => true,
                                'align' => 'center',
                                'fontSize' => 14,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 18,
                        'cells' => [

                            [
                                'width' => 100.0,
                                'text' => '{docTypeName}',
                                'bold' => true,
                                'italic' => false,
                                'uppercase' => true,
                                'align' => 'center',
                                'fontSize' => 10,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 18,
                        'cells' => [

                            [
                                'width' => 100.0,
                                'text' => '{title}',
                                'bold' => true,
                                'italic' => false,
                                'uppercase' => true,
                                'align' => 'center',
                                'fontSize' => 10,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 20,
                        'cells' => [

                            [
                                'width' => 45.0,
                                'text' => 'No. Dokumen : {documentNumber}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],

                            [
                                'width' => 26.0,
                                'text' => 'Terbit ke : {issueNumber}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],

                            [
                                'width' => 29.0,
                                'text' => 'Revisi ke : {revisionIndex}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 20,
                        'cells' => [

                            [
                                'width' => 45.0,
                                'text' => 'Tanggal Efektif : {issuedDate}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],

                            [
                                'width' => 55.0,
                                'text' => 'Halaman : {pageOf}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'key' => 'iso',
            'name' => 'Kop ISO',
            'description' => 'Logo di kiri, pita judul biru tua, dan enam isian metadata.',
            'layout' => [
                'version' => 2,
                'border' => '#404552',
                'frame' => true,
                'logo' => [
                    'enabled' => true,
                    'source' => 'company',
                    'assetId' => null,
                    'side' => 'left',
                    'rows' => 4,
                    'width' => 22,
                    'valign' => 'middle',
                    'padding' => 8,
                    'border' => true,
                ],
                'rows' => [

                    [
                        'height' => 20,
                        'cells' => [

                            [
                                'width' => 100.0,
                                'text' => '{docTypeName}',
                                'bold' => true,
                                'italic' => false,
                                'uppercase' => true,
                                'align' => 'center',
                                'fontSize' => null,
                                'background' => '#243661',
                                'color' => '#FFFFFF',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 20,
                        'cells' => [

                            [
                                'width' => 100.0,
                                'text' => '{title}',
                                'bold' => true,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'center',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 18,
                        'cells' => [

                            [
                                'width' => 58.33,
                                'text' => 'Nomor Dokumen : {documentNumber}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],

                            [
                                'width' => 41.67,
                                'text' => 'Tanggal Diterbitkan : {issuedDate}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 18,
                        'cells' => [

                            [
                                'width' => 58.33,
                                'text' => 'Nomor Revisi/Tahun : {revisionLabel}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],

                            [
                                'width' => 41.67,
                                'text' => 'Nomor Halaman : {page}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 18,
                        'cells' => [

                            [
                                'width' => 58.33,
                                'text' => 'Bahasa : {language}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],

                            [
                                'width' => 41.67,
                                'text' => 'Tingkat Dokumen : {documentLevel}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 18,
                        'cells' => [

                            [
                                'width' => 58.33,
                                'text' => 'Pemilik Proses : {processOwner}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],

                            [
                                'width' => 41.67,
                                'text' => 'Status Dokumen : {status}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'key' => 'sederhana',
            'name' => 'Kop sederhana',
            'description' => 'Logo di kiri dengan empat baris: jenis, judul, nomor, dan halaman.',
            'layout' => [
                'version' => 2,
                'border' => '#404552',
                'frame' => true,
                'logo' => [
                    'enabled' => true,
                    'source' => 'company',
                    'assetId' => null,
                    'side' => 'left',
                    'rows' => 4,
                    'width' => 20,
                    'valign' => 'middle',
                    'padding' => 8,
                    'border' => true,
                ],
                'rows' => [

                    [
                        'height' => 20,
                        'cells' => [

                            [
                                'width' => 100.0,
                                'text' => '{docTypeName}',
                                'bold' => true,
                                'italic' => false,
                                'uppercase' => true,
                                'align' => 'center',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 22,
                        'cells' => [

                            [
                                'width' => 100.0,
                                'text' => '{title}',
                                'bold' => true,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'center',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 18,
                        'cells' => [

                            [
                                'width' => 60.0,
                                'text' => 'Nomor : {documentNumber}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],

                            [
                                'width' => 40.0,
                                'text' => 'Revisi : {revisionLabel}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 18,
                        'cells' => [

                            [
                                'width' => 60.0,
                                'text' => 'Terbit : {issuedDate}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],

                            [
                                'width' => 40.0,
                                'text' => 'Halaman : {page}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'key' => 'tanpa-logo',
            'name' => 'Kop tanpa logo',
            'description' => 'Tiga baris selebar halaman, tanpa logo.',
            'layout' => [
                'version' => 2,
                'border' => '#404552',
                'frame' => true,
                'logo' => [
                    'enabled' => false,
                    'source' => 'company',
                    'assetId' => null,
                    'side' => 'left',
                    'rows' => 1,
                    'width' => 21,
                    'valign' => 'middle',
                    'padding' => 8,
                    'border' => true,
                ],
                'rows' => [

                    [
                        'height' => 20,
                        'cells' => [

                            [
                                'width' => 100.0,
                                'text' => '{docTypeName}',
                                'bold' => true,
                                'italic' => false,
                                'uppercase' => true,
                                'align' => 'center',
                                'fontSize' => null,
                                'background' => '#243661',
                                'color' => '#FFFFFF',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 20,
                        'cells' => [

                            [
                                'width' => 100.0,
                                'text' => '{title}',
                                'bold' => true,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'center',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 18,
                        'cells' => [

                            [
                                'width' => 60.0,
                                'text' => 'Nomor Dokumen : {documentNumber}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],

                            [
                                'width' => 40.0,
                                'text' => 'Status : {status}',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => true,
                                    'right' => true,
                                    'bottom' => true,
                                    'left' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'key' => 'logo-garis',
            'name' => 'Hanya logo + garis bawah',
            'description' => 'Logo di kiri tanpa tulisan dan tanpa kotak, dengan satu garis di bawahnya.',
            'layout' => [
                'version' => 2,
                'border' => '#404552',
                'frame' => false,
                'logo' => [
                    'enabled' => true,
                    'source' => 'company',
                    'assetId' => null,
                    'side' => 'left',
                    'rows' => 1,
                    'width' => 22,
                    'valign' => 'middle',
                    'padding' => 8,
                    'border' => false,
                ],
                'rows' => [

                    [
                        'height' => 48,
                        'cells' => [

                            [
                                'width' => 100.0,
                                'text' => '',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => false,
                                    'right' => false,
                                    'bottom' => false,
                                    'left' => false,
                                ],
                            ],
                        ],
                    ],

                    [
                        'height' => 12,
                        'cells' => [

                            [
                                'width' => 100.0,
                                'text' => '',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => false,
                                    'right' => false,
                                    'bottom' => true,
                                    'left' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'key' => 'kosong',
            'name' => 'Kosong',
            'description' => 'Satu baris tanpa isi dan tanpa garis, tanpa logo. Mulai dari sini untuk membuat kop sendiri.',
            'layout' => [
                'version' => 2,
                'border' => '#404552',
                'frame' => false,
                'logo' => [
                    'enabled' => false,
                    'source' => 'company',
                    'assetId' => null,
                    'side' => 'left',
                    'rows' => 1,
                    'width' => 21,
                    'valign' => 'middle',
                    'padding' => 8,
                    'border' => false,
                ],
                'rows' => [

                    [
                        'height' => 24,
                        'cells' => [

                            [
                                'width' => 100.0,
                                'text' => '',
                                'bold' => false,
                                'italic' => false,
                                'uppercase' => false,
                                'align' => 'left',
                                'fontSize' => null,
                                'background' => null,
                                'color' => '#1F212B',
                                'borders' => [
                                    'top' => false,
                                    'right' => false,
                                    'bottom' => false,
                                    'left' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
