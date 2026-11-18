<?php

namespace App\Http\Controllers\Documents\Config;

use App\Actions\Dcs\CreateDepartment;
use App\Actions\Dcs\UpdateDepartment;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Controllers\Documents\Config\Concerns\ResolvesConfigTenant;
use App\Http\Requests\Dcs\StoreDepartmentRequest;
use App\Http\Requests\Dcs\UpdateDepartmentRequest;
use App\Http\Resources\Documents\Config\DepartmentRowResource;
use App\Models\Dcs\Department;
use App\Support\Listing\DepartmentListSpec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Departments: part of every document number and the folder a document lives in.
 * Small enough to be edited in dialogs on the list itself.
 */
class DepartmentConfigController extends Controller
{
    use ResolvesConfigTenant;
    use RunsDcsActions;

    public function index(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $spec = new DepartmentListSpec;
        $list = $spec->parse($request);

        return Inertia::render('documents/config/departments/Index', [
            'departments' => DepartmentRowResource::collection($spec->paginate(Department::query()->where('tenant_id', $tenant->id)->withCount('documents'), $list)),
            'list' => $spec->meta($list),
        ]);
    }

    public function store(StoreDepartmentRequest $request, CreateDepartment $createDepartment): RedirectResponse
    {
        return $this->perform(fn () => $createDepartment->handle($request->user(), $this->tenant($request), $request->code(), $request->name()), 'Departemen ditambahkan.');
    }

    public function update(UpdateDepartmentRequest $request, string $department, UpdateDepartment $updateDepartment): RedirectResponse
    {
        $model = Department::query()->where('tenant_id', $this->tenant($request)->id)->where('uuid', $department)->firstOrFail();

        return $this->perform(fn () => $updateDepartment->handle($request->user(), $model, $request->name(), $request->isActive()), 'Departemen disimpan.');
    }
}
