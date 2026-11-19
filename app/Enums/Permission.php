<?php

namespace App\Enums;

/**
 * Every permission key in Smart CLM. Values must match the keys used by the
 * legacy application (docs/data/permissions.csv) so both systems agree.
 */
enum Permission: string
{
    case ContractEdit = 'contract.edit';
    case ContractDelete = 'contract.delete';
    case ContractMove = 'contract.move';
    case ContractClassify = 'contract.classify';
    case ContractBulk = 'contract.bulk';
    case ReviewAccess = 'review.access';
    case ReviewMarkupDecide = 'review.markup_decide';
    case ReviewSendExternal = 'review.send_external';
    case SigningManage = 'signing.manage';
    case ReleaseAnnounce = 'release.announce';
    case RetentionManage = 'retention.manage';
    case TemplatesManage = 'templates.manage';
    case DcsConfig = 'dcs.config';
    case DcsEdit = 'dcs.edit';
    case DcsRelease = 'dcs.release';
    case LegalJobRequest = 'legaljob.request';
    case LegalJobProcess = 'legaljob.process';
    case BudgetsManage = 'budgets.manage';
    case EmployeesManage = 'employees.manage';
    case VendorsManage = 'vendors.manage';
    case SettingsManage = 'settings.manage';
    case SettingsEmploymentTypes = 'settings.employment_types';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';
    case AiCompare = 'ai.compare';

    /**
     * Human readable description shown in the permission matrix.
     */
    public function label(): string
    {
        return match ($this) {
            self::ContractEdit => 'Buat, ubah & ajukan dokumen',
            self::ContractDelete => 'Hapus dokumen',
            self::ContractMove => 'Pindahkan dokumen ke folder/kategori arsip',
            self::ContractClassify => 'Ubah klasifikasi & nomor dokumen',
            self::ContractBulk => 'Impor massal & kirim pengingat massal',
            self::ReviewAccess => 'Mode Tinjau: komentar / coret / ganti',
            self::ReviewMarkupDecide => 'Terima / tolak usulan coret & ganti',
            self::ReviewSendExternal => 'Kirim/buka akses review pihak kedua & lewati review',
            self::SigningManage => 'Catat pengiriman/penerimaan TTD & unggah TTD basah',
            self::ReleaseAnnounce => 'Pengumuman rilis (internal & eksternal)',
            self::RetentionManage => 'Legal hold & pemusnahan dokumen',
            self::TemplatesManage => 'Kelola klausul, kategori klausul & template',
            self::DcsConfig => 'Atur jenis dokumen, aturan nomor & klausul DCS',
            self::DcsEdit => 'Buat & ubah dokumen DCS (versi, berkas, terjemahan)',
            self::DcsRelease => 'Rilis, distribusi & tarik dokumen DCS',
            self::LegalJobRequest => 'Ajukan Pekerjaan Legal',
            self::LegalJobProcess => 'Setujui/tolak Pekerjaan Legal & kelola link formulir',
            self::BudgetsManage => 'Kelola anggaran',
            self::EmployeesManage => 'Kelola data karyawan',
            self::VendorsManage => 'Kelola data vendor',
            self::SettingsManage => 'Kelola pengguna, pengaturan & master data',
            self::SettingsEmploymentTypes => 'Ubah Kategori Dokumen Karyawan saja',
            self::RolesView => 'Lihat peran & hak akses',
            self::RolesManage => 'Buat, ubah & hapus peran serta hak aksesnya',
            self::AiCompare => 'Bandingkan 2 dokumen dengan AI',
        };
    }

    /**
     * The section of the permission matrix this permission belongs to.
     */
    public function group(): string
    {
        return match ($this) {
            self::ContractEdit, self::ContractDelete, self::ContractMove,
            self::ContractClassify, self::ContractBulk => 'Dokumen (Eksternal & Karyawan)',
            self::ReviewAccess, self::ReviewMarkupDecide, self::ReviewSendExternal => 'Review & Persetujuan',
            self::SigningManage, self::ReleaseAnnounce, self::RetentionManage => 'TTD & Arsip',
            self::TemplatesManage => 'Library & Template',
            self::DcsConfig, self::DcsEdit, self::DcsRelease => 'Dokumen Terkontrol (DCS)',
            self::LegalJobRequest, self::LegalJobProcess => 'Pekerjaan Legal',
            self::BudgetsManage, self::EmployeesManage, self::VendorsManage => 'Data & Anggaran',
            self::SettingsManage, self::SettingsEmploymentTypes,
            self::RolesView, self::RolesManage => 'Pengaturan Sistem',
            self::AiCompare => 'AI Tools Hukum',
        };
    }

    /**
     * Permissions a tenant administrator role must always keep, so a tenant
     * can never lock itself out of user and role management.
     *
     * @return list<self>
     */
    public static function requiredByTenantAdmin(): array
    {
        return [self::SettingsManage, self::RolesView, self::RolesManage];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }

    /**
     * The catalog grouped by section, shaped for the permission matrix UI.
     *
     * @return list<array{group: string, permissions: list<array{value: string, label: string}>}>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = ['value' => $permission->value, 'label' => $permission->label()];
        }

        return array_map(
            fn (string $group, array $permissions): array => ['group' => $group, 'permissions' => $permissions],
            array_keys($groups),
            array_values($groups),
        );
    }
}
