<?php

namespace App\Http\Controllers\Documents\Config;

use App\Actions\Dcs\RemoveNumberingOverride;
use App\Actions\Dcs\SaveNumberingRule;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Controllers\Documents\Config\Concerns\ResolvesConfigTenant;
use App\Http\Requests\Dcs\SaveNumberingRuleRequest;
use App\Http\Resources\Documents\Config\NumberingRowResource;
use App\Models\Dcs\Department;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\NumberingRule;
use App\Support\Dcs\NumberingMask;
use App\Support\Listing\NumberingListSpec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Document numbering: the company's default mask and the types that have their
 * own. Changing a mask only affects numbers given from then on; each type's
 * counter keeps its own sequence.
 */
class NumberingConfigController extends Controller
{
    use ResolvesConfigTenant;
    use RunsDcsActions;

    public function index(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $default = NumberingRule::effectiveFor($tenant, null);
        $department = (string) (Department::query()->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('code')->value('code') ?? 'DEPT');
        $request->attributes->set('default_rule', $default);
        $request->attributes->set('sample_department', $department);

        $spec = new NumberingListSpec;
        $list = $spec->parse($request);
        $mask = $default instanceof NumberingRule ? $default->mask : NumberingMask::DEFAULT_MASK;

        return Inertia::render('documents/config/numbering/Index', [
            'types' => NumberingRowResource::collection($spec->paginate(DocumentType::query()->where('tenant_id', $tenant->id)->where('is_active', true)->with('numberingRules'), $list)),
            'list' => $spec->meta($list),
            'suggested_mask' => NumberingMask::SUGGESTED_MASK,
            'default_rule' => ['mask' => $mask, 'example' => NumberingRowResource::example($mask, 'SOP', $department)],
            'tokens' => [...array_map(fn (string $token): string => '{'.$token.'}', NumberingMask::TOKENS), '{Seq:2}', '{Rev:2}', '{Sequence:4}'],
        ]);
    }

    /**
     * Saves the default mask, or the one of a type when `docTypeId` is given.
     */
    public function update(SaveNumberingRuleRequest $request, SaveNumberingRule $saveNumberingRule): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $type = $request->docTypeId() === null ? null : $tenant->dcsDocumentTypes()->where('uuid', $request->docTypeId())->firstOrFail();

        return $this->perform(fn () => $saveNumberingRule->handle($request->user(), $tenant, $request->mask(), $type), $type === null ? 'Format nomor default disimpan.' : "Format nomor {$type->code} disimpan.");
    }

    public function destroy(Request $request, string $type, RemoveNumberingOverride $removeNumberingOverride): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $model = $tenant->dcsDocumentTypes()->where('uuid', $type)->firstOrFail();

        return $this->perform(fn () => $removeNumberingOverride->handle($request->user(), $tenant, $model), "Jenis {$model->code} kembali memakai format default.");
    }

    /**
     * An example number for a mask being typed, or what is wrong with it.
     */
    public function preview(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $mask = (string) $request->query('mask');
        $problem = NumberingMask::validate($mask);
        $department = (string) (Department::query()->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('code')->value('code') ?? 'DEPT');
        $typeId = (string) $request->query('type');
        $type = Str::isUuid($typeId) ? (string) ($tenant->dcsDocumentTypes()->where('uuid', $typeId)->value('code') ?? 'SOP') : 'SOP';

        return response()->json(['example' => $problem === null ? NumberingRowResource::example($mask, $type, $department) : null, 'error' => $problem]);
    }
}
