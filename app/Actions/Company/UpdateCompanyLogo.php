<?php

namespace App\Actions\Company;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Dcs\StoreHeaderAsset;
use App\Models\Dcs\TenantSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets the company logo (shown in the app and printed in the letterhead). The image is decoded and encoded
 * again by {@see StoreHeaderAsset}, so a file that only pretends to be an image fails and its metadata is dropped.
 * An earlier logo file is left on the disk: a letterhead may still use it as one of its own uploads.
 */
class UpdateCompanyLogo
{
    public function __construct(private StoreHeaderAsset $storeHeaderAsset, private RecordAuditLog $recordAuditLog) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Tenant $tenant, UploadedFile $file): TenantSetting
    {
        $asset = $this->storeHeaderAsset->handle($actor, $tenant, $file);

        return DB::transaction(function () use ($actor, $tenant, $asset): TenantSetting {
            $settings = TenantSetting::query()->where('tenant_id', $tenant->id)->first() ?? (new TenantSetting)->forceFill(['tenant_id' => $tenant->id]);
            $before = ['logo' => $settings->company_logo_key !== null];

            $settings->forceFill(['company_logo_key' => $asset->disk_key, 'company_logo_mime' => $asset->mime])->save();
            $this->recordAuditLog->handle($actor, 'company.logo.updated', $settings, $before, ['logo' => true, 'mime' => $asset->mime], $tenant->id);

            return $settings;
        });
    }
}
