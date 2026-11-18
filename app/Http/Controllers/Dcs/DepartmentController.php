<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\CreateDepartment;
use App\Actions\Dcs\UpdateDepartment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\StoreDepartmentRequest;
use App\Http\Requests\Dcs\UpdateDepartmentRequest;
use App\Http\Resources\Dcs\DepartmentResource;
use App\Support\Dcs\CurrentTenant;
use App\Support\Dcs\DcsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Departments are new in Laravel (the Node app took a free-text code); this
 * endpoint is additive and has no Node counterpart.
 */
class DepartmentController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function index(Request $request): JsonResponse
    {
        $departments = $this->currentTenant->get()->dcsDepartments()->where('is_active', true)->orderBy('code')->get();

        return response()->json(DepartmentResource::collection($departments)->resolve($request));
    }

    public function store(StoreDepartmentRequest $request, CreateDepartment $createDepartment): JsonResponse
    {
        $department = $createDepartment->handle($request->user(), $this->currentTenant->get(), $request->code(), $request->name());

        return response()->json(['success' => true, 'department' => DepartmentResource::make($department)->resolve($request)]);
    }

    public function update(UpdateDepartmentRequest $request, string $id, UpdateDepartment $updateDepartment): JsonResponse
    {
        $department = $this->currentTenant->get()->dcsDepartments()->where('uuid', $id)->first()
            ?? throw DcsException::notFound('Departemen tidak ditemukan');

        $department = $updateDepartment->handle($request->user(), $department, $request->name(), $request->isActive());

        return response()->json(['success' => true, 'department' => DepartmentResource::make($department)->resolve($request)]);
    }
}
