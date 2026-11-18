<?php

namespace App\Http\Controllers\Documents\Config\Concerns;

use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * The configuration pages belong to a company. A super admin has none, so
 * they are shown a clear refusal instead of somebody else's settings.
 */
trait ResolvesConfigTenant
{
    protected function tenant(Request $request): Tenant
    {
        return $request->user()->tenant ?? abort(403, 'Pengaturan dokumen dikelola oleh pengguna perusahaan. Masuk sebagai admin perusahaan untuk mengubahnya.');
    }
}
