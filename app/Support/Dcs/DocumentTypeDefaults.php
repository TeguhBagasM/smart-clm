<?php

namespace App\Support\Dcs;

use App\Enums\Dcs\LayoutStyle;

/**
 * The document types every tenant starts with, ported from the Node seed
 * (ensureTenantDefaults). Signer roles are system role keys; "manager" of the
 * Node seed is the legacy alias of "manajemen" and the new seed uses the latter,
 * so the people who hold it can actually sign (DEV-016).
 */
class DocumentTypeDefaults
{
    /**
     * Signature groups of a type that was created without any.
     *
     * @return list<array{heading: string|null, columns: list<array{label: string, role: string|null, roleName: string|null}>}>
     */
    public static function signatureGroups(): array
    {
        return [['heading' => null, 'columns' => [
            ['label' => 'Penyusun', 'role' => 'staff', 'roleName' => null],
            ['label' => 'Mengetahui', 'role' => 'manajemen', 'roleName' => null],
            ['label' => 'Menyetujui', 'role' => 'legal', 'roleName' => null],
        ]]];
    }

    /**
     * @return list<array{code: string, name: string, layout: LayoutStyle, letterhead: bool, sections: list<array{heading: string, content: string}>, groups: list<array{heading: string|null, columns: list<array{label: string, role: string|null, roleName: string|null}>}>}>
     */
    public static function types(): array
    {
        $standardSignatures = self::signatureGroups();

        return [
            [
                'code' => 'SOP', 'name' => 'Standard Operating Procedure', 'layout' => LayoutStyle::Standard, 'letterhead' => false,
                'sections' => self::sections(['1. Definisi', '2. Ketentuan Umum', '3. Prosedur / Langkah Kerja', '4. Referensi']),
                'groups' => $standardSignatures,
            ],
            [
                'code' => 'IK', 'name' => 'Instruksi Kerja', 'layout' => LayoutStyle::Standard, 'letterhead' => false,
                'sections' => self::sections(['1. Tujuan Instruksi', '2. Alat & Bahan', '3. Langkah-Langkah', '4. Keselamatan Kerja (K3)']),
                'groups' => $standardSignatures,
            ],
            [
                'code' => 'POL', 'name' => 'Kebijakan / Policy', 'layout' => LayoutStyle::Kebijakan, 'letterhead' => true,
                'sections' => self::sections(['1. Latar Belakang', '2. Pernyataan Kebijakan', '3. Ketentuan & Sanksi']),
                'groups' => [['heading' => null, 'columns' => [['label' => 'Disetujui oleh', 'role' => 'direktur', 'roleName' => null]]]],
            ],
            [
                'code' => 'MEMO', 'name' => 'Internal Memo', 'layout' => LayoutStyle::Memo, 'letterhead' => true,
                'sections' => self::sections(['Isi Memo']),
                'groups' => [
                    ['heading' => 'Penyusun:', 'columns' => [
                        ['label' => 'Manager HED', 'role' => 'manajemen', 'roleName' => null],
                        ['label' => 'Manager FAT', 'role' => 'manajemen', 'roleName' => null],
                    ]],
                    ['heading' => 'Mengetahui:', 'columns' => [
                        ['label' => 'CAO', 'role' => 'legal', 'roleName' => null],
                        ['label' => 'CEO', 'role' => 'direktur', 'roleName' => null],
                    ]],
                ],
            ],
            [
                'code' => 'STD', 'name' => 'Standarisasi', 'layout' => LayoutStyle::Standard, 'letterhead' => false,
                'sections' => self::sections([
                    '1. Ruang Lingkup', '2. Definisi & Istilah', '3. Persyaratan / Kriteria Standar',
                    '4. Metode Verifikasi & Pemantauan', '5. Referensi Standar',
                ]),
                'groups' => $standardSignatures,
            ],
        ];
    }

    /**
     * @param  list<string>  $headings
     * @return list<array{heading: string, content: string}>
     */
    private static function sections(array $headings): array
    {
        return array_map(fn (string $heading): array => ['heading' => $heading, 'content' => ''], $headings);
    }
}
