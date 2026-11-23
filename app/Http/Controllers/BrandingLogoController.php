<?php

namespace App\Http\Controllers;

use App\Support\Branding\CompanyBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The company logo shown in the sidebar, on the sign-in page and as the browser icon. Open to visitors
 * (the sign-in page needs it); which company's logo is decided by {@see CompanyBranding}.
 */
class BrandingLogoController extends Controller
{
    public function show(Request $request, CompanyBranding $branding): StreamedResponse
    {
        $settings = $branding->settingsFor($request->user());
        abort_if($settings === null, 404);

        return Storage::disk(config('dcs.disk'))->response($settings->company_logo_key, null, [
            'Content-Type' => (string) $settings->company_logo_mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
