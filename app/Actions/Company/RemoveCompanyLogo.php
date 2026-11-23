<?php

namespace App\Actions\Company;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\TenantSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Takes the company logo away; the app and the letterhead go back to the default icon. The file stays on the disk.
 */
class RemoveCompanyLogo
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Tenant $tenant): void
    {
        DB::transaction(function () use ($actor, $tenant): void {
            $settings = TenantSetting::query()->where('tenant_id', $tenant->id)->first();

            if ($settings?->company_logo_key === null) {
                return;
            }

            $settings->forceFill(['company_logo_key' => null, 'company_logo_mime' => null])->save();
            $this->recordAuditLog->handle($actor, 'company.logo.removed', $settings, ['logo' => true], ['logo' => false], $tenant->id);
        });
    }
}
