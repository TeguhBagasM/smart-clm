<?php

namespace App\Http\Controllers\Dcs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\PreviewNumberRequest;
use App\Models\Dcs\NumberingRule;
use App\Support\Dcs\CurrentTenant;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\NumberAllocator;
use App\Support\Dcs\NumberingMask;
use Illuminate\Http\JsonResponse;

/**
 * Shows the number the next document would get, before it is created. It only
 * peeks: nothing is reserved, so the real number can differ if another document
 * of the same scope is created in between.
 */
class NumberPreviewController extends Controller
{
    public function __invoke(PreviewNumberRequest $request, CurrentTenant $currentTenant, NumberAllocator $numberAllocator): JsonResponse
    {
        $tenant = $currentTenant->get();
        $type = $tenant->dcsDocumentTypes()->where('code', $request->docTypeCode())->first()
            ?? throw DcsException::badRequest('Unknown document type: '.$request->docTypeCode());
        $rule = NumberingRule::effectiveFor($tenant, $type) ?? throw DcsException::badRequest('Aturan penomoran belum tersedia untuk perusahaan ini.');

        $tokens = ['DocType' => $type->code, 'Department' => $request->department(), ...$numberAllocator->clockTokens()];

        return response()->json([
            'documentNumber' => NumberingMask::render($rule->mask, $tokens, $numberAllocator->peek($rule, $tokens)),
        ]);
    }
}
