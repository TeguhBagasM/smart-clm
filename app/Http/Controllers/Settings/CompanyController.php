<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Company\RemoveCompanyLogo;
use App\Actions\Company\UpdateCompanyLogo;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateCompanyLogoRequest;
use App\Support\Branding\CompanyBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The company's own settings page: today its logo. Behind `settings.manage`; a super admin belongs to no
 * company and is refused.
 */
class CompanyController extends Controller
{
    public function edit(Request $request, CompanyBranding $branding): Response
    {
        $this->authorizeCompanyUser($request);

        return Inertia::render('settings/Company', [
            'company' => ['name' => $request->user()->tenant->name],
            'logo_url' => $branding->logoUrl($request->user()),
        ]);
    }

    public function update(UpdateCompanyLogoRequest $request, UpdateCompanyLogo $updateCompanyLogo): RedirectResponse
    {
        $updateCompanyLogo->handle($request->user(), $request->user()->tenant, $request->file('file'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo perusahaan diperbarui.']);

        return back();
    }

    public function destroy(Request $request, RemoveCompanyLogo $removeCompanyLogo): RedirectResponse
    {
        $this->authorizeCompanyUser($request);
        $removeCompanyLogo->handle($request->user(), $request->user()->tenant);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo perusahaan dihapus. Aplikasi memakai ikon bawaan.']);

        return back();
    }

    private function authorizeCompanyUser(Request $request): void
    {
        abort_unless($request->user()->tenant_id !== null && $request->user()->can(Permission::SettingsManage), 403);
    }
}
