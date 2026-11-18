<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\RemoveNumberingOverride;
use App\Actions\Dcs\SaveNumberingRule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\SaveNumberingRuleRequest;
use App\Http\Resources\Dcs\NumberingRuleResource;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\NumberingRule;
use App\Support\Dcs\CurrentTenant;
use App\Support\Dcs\DcsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NumberingRuleController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function show(Request $request): JsonResponse
    {
        $type = $this->findType($request->query('docTypeId'));
        $rule = NumberingRule::effectiveFor($this->currentTenant->get(), $type) ?? throw DcsException::notFound('Aturan penomoran belum tersedia untuk perusahaan ini.');

        return response()->json(NumberingRuleResource::make($rule->load('tenant'))->resolve($request));
    }

    public function update(SaveNumberingRuleRequest $request, SaveNumberingRule $saveNumberingRule): JsonResponse
    {
        $tenant = $this->currentTenant->get();
        $type = $request->docTypeId() === null ? null : ($this->findType($request->docTypeId()) ?? throw DcsException::notFound('Jenis dokumen tidak ditemukan'));

        $rule = $saveNumberingRule->handle($request->user(), $tenant, $request->mask(), $type);

        return response()->json(['success' => true, 'rule' => NumberingRuleResource::make($rule->load('tenant'))->resolve($request)]);
    }

    public function destroy(Request $request, RemoveNumberingOverride $removeNumberingOverride): JsonResponse
    {
        $tenant = $this->currentTenant->get();
        $id = $request->query('docTypeId');

        if (! is_string($id) || $id === '') {
            throw DcsException::badRequest('docTypeId wajib diisi untuk mengembalikan ke format default.');
        }

        $type = $this->findType($id);

        if ($type !== null) {
            $removeNumberingOverride->handle($request->user(), $tenant, $type);
        }

        $rule = NumberingRule::effectiveFor($tenant, $type) ?? throw DcsException::notFound('Aturan penomoran belum tersedia untuk perusahaan ini.');

        return response()->json(['success' => true, 'rule' => NumberingRuleResource::make($rule->load('tenant'))->resolve($request)]);
    }

    private function findType(mixed $uuid): ?DocumentType
    {
        return is_string($uuid) && Str::isUuid($uuid)
            ? $this->currentTenant->get()->dcsDocumentTypes()->where('uuid', $uuid)->first()
            : null;
    }
}
