<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\TranslateVersion;
use App\Http\Controllers\Controller;
use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VersionTranslateController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function store(Request $request, string $id, TranslateVersion $translateVersion): JsonResponse
    {
        $translateVersion->handle($request->user(), DocumentVersion::forTenant($this->currentTenant->get(), $id));

        return response()->json(['success' => true, 'lang' => 'en']);
    }
}
