<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\TenantSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the company's saved colours (the "warna perusahaan" of the colour picker).
 */
class UpdateBrandColors
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  list<array{hex: string, name: string}>  $colors
     */
    public function handle(User $actor, Tenant $tenant, array $colors): TenantSetting
    {
        return DB::transaction(function () use ($actor, $tenant, $colors): TenantSetting {
            $settings = TenantSetting::query()->where('tenant_id', $tenant->id)->first() ?? (new TenantSetting)->forceFill(['tenant_id' => $tenant->id]);
            $before = ['brand_colors' => $settings->brand_colors ?? []];

            $settings->forceFill(['brand_colors' => $colors])->save();

            $this->recordAuditLog->handle($actor, 'dcs.brand_colors.updated', $settings, $before, ['brand_colors' => $colors], $tenant->id);

            return $settings;
        });
    }
}
