<?php

namespace App\Http\Controllers\MasterData;

use App\Actions\MasterData\SaveEmployee;
use App\Actions\MasterData\SetEmployeeActive;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MasterData\Concerns\ResolvesCompany;
use App\Http\Requests\MasterData\SaveEmployeeRequest;
use App\Http\Resources\MasterData\EmployeeResource;
use App\Models\MasterData\Employee;
use App\Models\User;
use App\Support\Listing\EmployeeListSpec;
use App\Support\MasterData\DependentOptions;
use App\Support\MasterData\ListPage;
use App\Support\MasterData\MasterDataCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The employee directory (personal data): list, add, change, hide and show
 * again. Only employees.manage holders reach it; no salary, no national id.
 */
class EmployeeController extends Controller
{
    use ResolvesCompany;

    public function index(Request $request, DependentOptions $options, ListPage $page, MasterDataCatalog $catalog): Response|RedirectResponse|SymfonyResponse
    {
        if (! Gate::forUser($request->user())->allows('viewAny', Employee::class)) {
            return $this->forbidden($request);
        }

        $tenant = $this->company($request);

        if ($tenant === null) {
            $this->toast('choose_company', type: 'error');

            return redirect()->route('master-data.index');
        }

        $departments = $options->departments($tenant);
        $employmentTypes = $options->employmentTypes($tenant);
        $spec = new EmployeeListSpec($page->stringOptions($departments), $page->stringOptions($employmentTypes));
        $parsed = $spec->parse($request);
        $query = $tenant->employees()->with(['department:id,name', 'employmentType:id,name', 'user:id,name'])->getQuery();

        return Inertia::render('master-data/Employees', [
            'items' => EmployeeResource::collection($spec->paginate($query, $parsed)),
            'list' => $spec->meta($parsed),
            'info' => $page->info('employees'),
            'options' => [
                'departments' => $departments,
                'employment_types' => $employmentTypes,
                'users' => Gate::allows('viewAny', User::class) ? $tenant->users()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name])->all() : null,
            ],
            'company' => ['id' => $tenant->id, 'name' => $tenant->name],
            'companies' => $catalog->companies($request->user()),
        ]);
    }

    public function store(SaveEmployeeRequest $request, SaveEmployee $save): RedirectResponse
    {
        $save->handle($request->user(), $request->tenant(), $request->employeeData());
        $this->toast('created', ['label' => config()->string('master_data.lists.employees.label')]);

        return back();
    }

    public function update(SaveEmployeeRequest $request, Employee $record, SaveEmployee $save): RedirectResponse
    {
        $save->handle($request->user(), $request->tenant(), $request->employeeData(), $record);
        $this->toast('updated', ['label' => config()->string('master_data.lists.employees.label')]);

        return back();
    }

    public function deactivate(Request $request, Employee $record, SetEmployeeActive $setActive): RedirectResponse
    {
        Gate::authorize('deactivate', $record);
        $setActive->handle($request->user(), $record, false);
        $this->toast('deactivated', ['label' => config()->string('master_data.lists.employees.label')]);

        return back();
    }

    public function reactivate(Request $request, Employee $record, SetEmployeeActive $setActive): RedirectResponse
    {
        Gate::authorize('reactivate', $record);
        $setActive->handle($request->user(), $record, true);
        $this->toast('reactivated', ['label' => config()->string('master_data.lists.employees.label')]);

        return back();
    }
}
