<?php

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use App\Http\Requests\Documents\StoreHeaderAssetRequest;

/**
 * The company logo: the same file rules as a letterhead logo (PNG, JPG or WebP, 2 MB, 4000 px). Only a user who
 * manages settings and belongs to a company may change it.
 */
class UpdateCompanyLogoRequest extends StoreHeaderAssetRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tenant_id !== null && $this->user()->can(Permission::SettingsManage);
    }
}
