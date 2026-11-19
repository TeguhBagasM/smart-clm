<?php

namespace App\Http\Controllers\Documents\Config;

use App\Actions\Dcs\StoreHeaderAsset;
use App\Actions\Dcs\UpdateBrandColors;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Config\Concerns\ResolvesConfigTenant;
use App\Http\Requests\Documents\StoreHeaderAssetRequest;
use App\Http\Requests\Documents\UpdateBrandColorsRequest;
use App\Models\Dcs\HeaderAsset;
use App\Models\Dcs\TenantSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Images of the letterhead editor and the company's saved colours. Every
 * file is looked up under the signed-in user's own company, so another company's
 * logo answers 404, never the file.
 */
class HeaderAssetController extends Controller
{
    use ResolvesConfigTenant;

    public function store(StoreHeaderAssetRequest $request, StoreHeaderAsset $storeHeaderAsset): JsonResponse
    {
        $asset = $storeHeaderAsset->handle($request->user(), $this->tenant($request), $request->file('file'));

        return response()->json(['id' => $asset->uuid, 'url' => route('documents.config.header-assets.show', $asset->uuid), 'width' => $asset->width_px, 'height' => $asset->height_px], 201);
    }

    public function show(Request $request, string $asset): StreamedResponse
    {
        $model = HeaderAsset::query()->where('tenant_id', $this->tenant($request)->id)->where('uuid', $asset)->firstOrFail();

        return Storage::disk(config('dcs.disk'))->response($model->disk_key, null, ['Content-Type' => $model->mime, 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function company(Request $request): StreamedResponse
    {
        $settings = TenantSetting::query()->where('tenant_id', $this->tenant($request)->id)->first();
        abort_if($settings?->company_logo_key === null, 404);

        return Storage::disk(config('dcs.disk'))->response($settings->company_logo_key, null, ['Content-Type' => (string) $settings->company_logo_mime, 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function colors(UpdateBrandColorsRequest $request, UpdateBrandColors $updateBrandColors): JsonResponse
    {
        $settings = $updateBrandColors->handle($request->user(), $this->tenant($request), $request->colors());

        return response()->json(['colors' => $settings->brand_colors]);
    }
}
