<?php

namespace App\Support\Navigation;

use App\Enums\Permission;
use App\Models\AiTools\DocumentComparison;
use App\Models\Dcs\Document;
use App\Models\MasterData\Vendor;
use App\Models\User;
use App\Support\MasterData\MasterDataCatalog;
use Closure;

/**
 * The sidebar, one module per dropdown, decided on the server so a menu only
 * appears when its page would let the user in. Modules still being migrated
 * from the Node app are listed as placeholders (page "Sedang dikembangkan"),
 * visible to super admins only, so the migration map shows in the app.
 *
 * To ship a module: give it a real route, then replace its `placeholder`
 * entry with `route` and the ability that guards that route.
 */
class NavigationMenu
{
    /**
     * Ability that shows the placeholders (super admin only, until each
     * module has a permission of its own).
     */
    public const string InDevelopmentAbility = 'viewModulesInDevelopment';

    /**
     * Modules and the menus each user can open.
     *
     * @return list<array{key: string, title: string, icon: string, items: list<array{title: string, icon: string, href: string, active_prefix: string|null, in_development: bool}>}>
     */
    public function for(User $user): array
    {
        $modules = [];

        foreach ($this->catalog() as $module) {
            $items = [];

            foreach ($module['items'] as $item) {
                if (isset($item['placeholder'])) {
                    if ($user->can(self::InDevelopmentAbility)) {
                        $items[] = [
                            'title' => $item['title'],
                            'icon' => $item['icon'],
                            'href' => route('modules.show', ['module' => $module['key'], 'page' => $item['placeholder']], absolute: false),
                            'active_prefix' => null,
                            'in_development' => true,
                        ];
                    }

                    continue;
                }

                if (($item['visible'])($user)) {
                    $items[] = [
                        'title' => $item['title'],
                        'icon' => $item['icon'],
                        'href' => route($item['route'], absolute: false),
                        'active_prefix' => $item['active_prefix'] ?? null,
                        'in_development' => false,
                    ];
                }
            }

            if ($items !== []) {
                $modules[] = ['key' => $module['key'], 'title' => $module['title'], 'icon' => $module['icon'], 'items' => $items];
            }
        }

        return $modules;
    }

    /**
     * The module and page titles of a placeholder, or null when there is none.
     *
     * @return array{module: string, page: string, icon: string}|null
     */
    public function placeholder(string $moduleKey, string $pageKey): ?array
    {
        foreach ($this->catalog() as $module) {
            if ($module['key'] !== $moduleKey) {
                continue;
            }

            foreach ($module['items'] as $item) {
                if (($item['placeholder'] ?? null) === $pageKey) {
                    return ['module' => $module['title'], 'page' => $item['title'], 'icon' => $item['icon']];
                }
            }
        }

        return null;
    }

    /**
     * Every module, in sidebar order. Items carry either `placeholder` (a page
     * key under /modules/{module}/) or `route` plus `visible`.
     *
     * @return list<array{key: string, title: string, icon: string, items: list<array{title: string, icon: string, placeholder?: string, route?: string, active_prefix?: string, visible?: Closure(User): bool}>}>
     */
    private function catalog(): array
    {
        return [
            [
                'key' => 'dokumen-eksternal', 'title' => 'Dokumen Eksternal', 'icon' => 'briefcase',
                'items' => [
                    $this->dashboard(),
                    $this->soon('pekerjaan-legal', 'Pekerjaan Legal', 'clipboard-list'),
                    $this->soon('monitoring-kontrak', 'Monitoring Kontrak', 'bell'),
                ],
            ],
            [
                'key' => 'dokumen-internal', 'title' => 'Dokumen Internal', 'icon' => 'building',
                'items' => [
                    $this->dashboard(),
                    ['title' => 'Tugas saya', 'icon' => 'clipboard-check', 'route' => 'documents.queue', 'visible' => fn (User $user): bool => $user->can('viewQueue', Document::class)],
                    ['title' => 'Dokumen Terkontrol', 'icon' => 'file-text', 'route' => 'documents.index', 'visible' => fn (User $user): bool => $user->can('viewAny', Document::class)],
                    $this->soon('dokumen-karyawan', 'Dokumen Karyawan', 'users'),
                ],
            ],
            [
                'key' => 'arsip-daftar', 'title' => 'Arsip & Daftar', 'icon' => 'archive',
                'items' => [
                    $this->dashboard(),
                    $this->soon('arsip-eksternal', 'Arsip Eksternal', 'folder-open'),
                    $this->soon('arsip-internal', 'Arsip Internal', 'folder-open'),
                    $this->soon('semua-dokumen', 'Semua Dokumen', 'table'),
                    $this->soon('audit-trail', 'Audit Trail', 'history'),
                    $this->soon('audit-kontrak-dcs', 'Audit Kontrak & DCS', 'check-square'),
                ],
            ],
            [
                'key' => 'master-data', 'title' => 'Master Data', 'icon' => 'database',
                'items' => [
                    $this->dashboard(),
                    ['title' => 'Pengaturan Dokumen', 'icon' => 'settings', 'route' => 'documents.config.types.index', 'active_prefix' => '/documents/config', 'visible' => fn (User $user): bool => $user->can('configure', Document::class)],
                    $this->soon('klausul-template', 'Klausul & Template', 'sliders'),
                    ['title' => 'Daftar Pilihan', 'icon' => 'folder-tree', 'route' => 'master-data.index', 'active_prefix' => '/master-data', 'visible' => fn (User $user): bool => app(MasterDataCatalog::class)->canOpenAny($user)],
                    ['title' => 'Data Vendor', 'icon' => 'store', 'route' => 'master-data.vendors.index', 'active_prefix' => '/master-data/vendors', 'visible' => fn (User $user): bool => $user->can('viewAny', Vendor::class)],
                ],
            ],
            [
                'key' => 'ai-tools', 'title' => 'AI Tools Hukum', 'icon' => 'sparkles',
                'items' => [
                    $this->dashboard(),
                    $this->soon('pusat-peraturan', 'Pusat Peraturan', 'scale'),
                    $this->soon('putusan-pengadilan', 'Putusan Pengadilan', 'gavel'),
                    $this->soon('opini-hukum', 'Opini Hukum', 'message-square'),
                    $this->soon('terjemah-dokumen', 'Terjemah Dokumen', 'languages'),
                    $this->soon('audit-risiko-naskah', 'Audit Risiko Naskah', 'search'),
                    ['title' => 'Bandingkan 2 Dokumen', 'icon' => 'arrow-left-right', 'route' => 'ai-tools.comparisons.create', 'active_prefix' => '/ai-tools/comparisons', 'visible' => fn (User $user): bool => $user->can('viewAny', DocumentComparison::class)],
                    $this->soon('buat-draft', 'Buat Draft AI', 'pen-line'),
                    $this->soon('analisis-tabular', 'Analisis Tabular', 'table'),
                    $this->soon('analytics-risk', 'Analytics & Risk', 'line-chart'),
                ],
            ],
            [
                'key' => 'pengaturan-sistem', 'title' => 'Pengaturan Sistem', 'icon' => 'settings',
                'items' => [
                    $this->dashboard(),
                    ['title' => 'Pengguna', 'icon' => 'users', 'route' => 'admin.users.index', 'visible' => fn (User $user): bool => $user->can(Permission::SettingsManage)],
                    ['title' => 'Hak Akses', 'icon' => 'key-round', 'route' => 'admin.roles.index', 'visible' => fn (User $user): bool => $user->can(Permission::RolesView)],
                    $this->soon('perusahaan', 'Kelola Perusahaan', 'building-2'),
                    $this->soon('perusahaan-e-meterai', 'Perusahaan & e-Meterai', 'stamp'),
                ],
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, placeholder: string}
     */
    private function dashboard(): array
    {
        return $this->soon('dashboard', 'Dashboard', 'layout-grid');
    }

    /**
     * @return array{title: string, icon: string, placeholder: string}
     */
    private function soon(string $page, string $title, string $icon): array
    {
        return ['title' => $title, 'icon' => $icon, 'placeholder' => $page];
    }
}
