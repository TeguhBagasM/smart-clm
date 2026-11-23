<?php

namespace App\Support\Dcs\Header;

/**
 * What a letterhead cell can show. The system fills these in; people only choose
 * which cell shows which field. `Status` and `IssuedDate` change after the PDF is
 * composed, so the engine leaves them blank and they are stamped when the file is viewed.
 */
enum HeaderField: string
{
    case Text = 'text';
    case DocumentNumber = 'documentNumber';
    case Title = 'title';
    case DocTypeName = 'docTypeName';
    case DocTypeCode = 'docTypeCode';
    case IssueNumber = 'issueNumber';
    case RevisionNumber = 'revisionNumber';
    case RevisionLabel = 'revisionLabel';
    case IssuedDate = 'issuedDate';
    case Status = 'status';
    case Page = 'page';
    case Language = 'language';
    case DocumentLevel = 'documentLevel';
    case ProcessOwner = 'processOwner';
    case Department = 'department';
    case Owner = 'owner';
    case CompanyName = 'companyName';
    case RevisionIndex = 'revisionIndex';
    case PageOf = 'pageOf';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Teks tetap (tanpa isian)',
            self::DocumentNumber => 'Nomor dokumen',
            self::Title => 'Judul dokumen',
            self::DocTypeName => 'Jenis dokumen',
            self::DocTypeCode => 'Kode jenis dokumen',
            self::IssueNumber => 'Nomor terbitan',
            self::RevisionNumber => 'Nomor revisi',
            self::RevisionLabel => 'Nomor revisi/tahun',
            self::IssuedDate => 'Tanggal diterbitkan',
            self::Status => 'Status dokumen',
            self::Page => 'Nomor halaman',
            self::Language => 'Bahasa',
            self::DocumentLevel => 'Tingkat dokumen',
            self::ProcessOwner => 'Pemilik proses',
            self::Department => 'Departemen',
            self::Owner => 'Pemilik dokumen',
            self::CompanyName => 'Nama perusahaan',
            self::RevisionIndex => 'Revisi ke (angka biasa)',
            self::PageOf => 'Halaman (1 dari 5)',
        };
    }

    /** What the preview shows before a real document exists. */
    public function sample(): string
    {
        return match ($this) {
            self::Text => '',
            self::DocumentNumber => 'SOP/HRD/2026/0001',
            self::Title => 'Prosedur Onboarding Karyawan',
            self::DocTypeName => 'Standard Operating Procedure',
            self::DocTypeCode => 'SOP',
            self::IssueNumber => '1',
            self::RevisionNumber => '00',
            self::RevisionLabel => '00/26',
            self::IssuedDate => '28 Maret 2026',
            self::Status => 'TERKENDALI (CONTROLLED)',
            self::Page => 'Page 1 Of 3',
            self::Language => 'Indonesia',
            self::DocumentLevel => 'Internal',
            self::ProcessOwner => 'HED Department',
            self::Department => 'HRD',
            self::Owner => 'Nama Pemilik',
            self::CompanyName => 'PT Nama Perusahaan',
            self::RevisionIndex => '1',
            self::PageOf => '1 dari 3',
        };
    }

    /** Filled in when the file is viewed, not when it is composed. */
    public function isReactive(): bool
    {
        return $this === self::Status || $this === self::IssuedDate;
    }

    /**
     * The catalog the editor offers.
     *
     * @return list<array{value: string, label: string, sample: string}>
     */
    public static function catalog(): array
    {
        return array_map(fn (self $field): array => [
            'value' => $field->value,
            'label' => $field->label(),
            'sample' => $field->sample(),
        ], self::cases());
    }
}
