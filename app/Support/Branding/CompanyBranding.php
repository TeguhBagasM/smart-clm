<?php

namespace App\Support\Branding;

use App\Models\Dcs\TenantSetting;
use App\Models\Tenant;
use App\Models\User;

/**
 * Whose logo the application shows. A signed-in user sees their own company's logo. A visitor who is not
 * signed in, and a super admin (who belongs to no company), see the logo of the only company when there is
 * exactly one; with several companies there is no way to tell, so the default icon shows.
 */
class CompanyBranding
{
    public function tenantFor(?User $user): ?Tenant
    {
        if ($user?->tenant_id !== null) {
            return $user->tenant;
        }

        $tenants = Tenant::query()->where('is_active', true)->limit(2)->get();

        return $tenants->count() === 1 ? $tenants->first() : null;
    }

    public function settingsFor(?User $user): ?TenantSetting
    {
        $tenant = $this->tenantFor($user);

        if ($tenant === null) {
            return null;
        }

        $settings = TenantSetting::query()->where('tenant_id', $tenant->id)->first();

        return $settings?->company_logo_key === null ? null : $settings;
    }

    /**
     * The address of the logo, with a short fingerprint so a replaced logo is not served from the browser's cache.
     */
    public function logoUrl(?User $user): ?string
    {
        $settings = $this->settingsFor($user);

        return $settings === null ? null : route('branding.logo', ['v' => substr(sha1((string) $settings->company_logo_key), 0, 10)]);
    }
}
