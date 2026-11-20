<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\MasterData\DocumentModule;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Employee;
use App\Support\MasterData\ActingCompany;
use App\Support\MasterData\DependentOptions;
use App\Support\MasterData\Employees\EmployeeDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The choices of a form, as JSON, for the company of the user (a super admin
 * names it with `tenant_id`): only active rows, narrowed by the earlier choice
 * (platform for partners, module and category for categories and document
 * types). Every user of the company may read them, except the employee list,
 * which is personal data and needs employees.manage. Vendors are names only.
 */
class FormOptionsController extends Controller
{
    public function show(Request $request, string $kind, ActingCompany $company, DependentOptions $options, EmployeeDirectory $directory): JsonResponse
    {
        $tenant = $company->resolve($request->user(), $request->query('tenant_id'));
        $module = fn (): DocumentModule => DocumentModule::from($request->string('module')->toString());

        $request->validate(match ($kind) {
            'partners' => ['platform_id' => ['required', 'integer']],
            'categories' => ['module' => ['required', Rule::enum(DocumentModule::class)]],
            'document-types' => ['module' => ['required', Rule::enum(DocumentModule::class)], 'category_id' => ['nullable', 'integer']],
            default => [],
        }, ['required' => 'Parameter :attribute wajib diisi.', 'enum' => 'Parameter :attribute tidak dikenal.']);

        $data = match ($kind) {
            'platforms' => $options->platforms($tenant),
            'partners' => $options->partners($tenant, $request->integer('platform_id')),
            'categories' => $options->categories($tenant, $module()),
            'document-types' => $options->documentTypes($tenant, $module(), $request->filled('category_id') ? $request->integer('category_id') : null),
            'employment-types' => $options->employmentTypes($tenant),
            'party-types' => $options->partyTypes($tenant),
            'currencies' => $options->currencies($tenant),
            'departments' => $options->departments($tenant),
            'vendors' => $options->vendors($tenant),
            'employees' => Gate::forUser($request->user())->authorize('viewAny', Employee::class) ? $directory->options($tenant) : [],
            default => abort(404),
        };

        return response()->json(['data' => $data]);
    }
}
