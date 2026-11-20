<?php

namespace App\Support\MasterData\Legacy;

use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\Tenant;

/**
 * Node `masterData.operatorGroups` into partner groups and partners. A group
 * lists its platforms by name; a group that names only platforms the company
 * does not have is skipped (importing it without them would make it apply to
 * every platform). The other companies a Node group listed, and the contact and
 * bank details of each operator, are not imported.
 */
class LegacyPartnerImporter
{
    /**
     * @param  list<mixed>  $groups
     */
    public function import(Tenant $tenant, array $groups, ImportReport $report): void
    {
        $platforms = $tenant->platforms()->pluck('id', 'name')->mapWithKeys(fn (int $id, string $name): array => [mb_strtolower($name) => $id]);

        foreach ($groups as $index => $node) {
            $node = is_array($node) ? $node : [];
            $key = trim((string) ($node['id'] ?? '')) ?: 'name:'.mb_strtolower(trim((string) ($node['name'] ?? '')));
            $name = trim((string) ($node['name'] ?? ''));
            $named = array_values(array_filter(array_map(fn (mixed $p): string => trim((string) $p), (array) ($node['platforms'] ?? [])), fn (string $p): bool => $p !== ''));
            $ids = [];

            foreach ($named as $platform) {
                $id = $platforms->get(mb_strtolower($platform));
                $id === null ? $report->unknown('platform di kelompok mitra', $platform) : $ids[] = $id;
            }

            if ($named !== [] && $ids === []) {
                $report->error("partner-groups:{$key}", 'semua platformnya belum ada di perusahaan ini, kelompok dilewati');
                $report->count('partner-groups', 'skipped');

                continue;
            }

            $group = PartnerGroup::query()->where('tenant_id', $tenant->id)->where('legacy_key', $key)->first();

            if ($group === null) {
                if ($name === '' || mb_strlen($name) > 150 || PartnerGroup::query()->where('tenant_id', $tenant->id)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
                    $report->error("partner-groups:{$key}", 'nama kosong, terlalu panjang, atau sudah dipakai kelompok lain');
                    $report->count('partner-groups', 'skipped');

                    continue;
                }

                $group = new PartnerGroup;
                $group->forceFill(['tenant_id' => $tenant->id, 'name' => $name, 'sort_order' => ((int) PartnerGroup::query()->where('tenant_id', $tenant->id)->max('sort_order')) + 1, 'is_active' => true, 'legacy_key' => $key])->save();
                $group->platforms()->sync(array_values(array_unique($ids)));
                $report->count('partner-groups', 'created');
            } else {
                $report->count('partner-groups', 'unchanged');
            }

            $this->partners($tenant, $group, (array) ($node['operators'] ?? []), $report);
            $this->noteIgnored($node, $report, $key);
        }
    }

    /**
     * @param  array<mixed>  $operators
     */
    private function partners(Tenant $tenant, PartnerGroup $group, array $operators, ImportReport $report): void
    {
        foreach ($operators as $operator) {
            $name = trim((string) $operator);

            if ($name === '' || mb_strlen($name) > 150) {
                $report->error("partners:{$group->legacy_key}", 'nama mitra kosong atau lebih dari 150 karakter');
                $report->count('partners', 'skipped');

                continue;
            }

            if (Partner::query()->where('partner_group_id', $group->id)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
                $report->count('partners', 'unchanged');

                continue;
            }

            $partner = new Partner;
            $partner->forceFill([
                'tenant_id' => $tenant->id, 'partner_group_id' => $group->id, 'name' => $name, 'is_active' => true,
                'sort_order' => ((int) Partner::query()->where('partner_group_id', $group->id)->max('sort_order')) + 1,
                'legacy_key' => mb_substr($group->legacy_key.'|'.mb_strtolower($name), 0, 200),
            ])->save();
            $report->count('partners', 'created');
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function noteIgnored(array $node, ImportReport $report, string $key): void
    {
        $details = count(array_filter((array) ($node['operatorDetails'] ?? [])));

        if ($details > 0) {
            $report->warn("partner-groups:{$key}: {$details} data kontak/rekening mitra tidak diimpor (sengaja)");
        }

        if ((array) ($node['companies'] ?? []) !== []) {
            $report->warn("partner-groups:{$key}: daftar PT lain pada kelompok diabaikan (satu kelompok milik satu PT)");
        }
    }
}
