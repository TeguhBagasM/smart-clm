<?php

use App\Enums\MasterData\MasterList;
use App\Enums\SystemRole;
use App\Http\Requests\MasterData\SaveCategoryRequest;
use App\Http\Requests\MasterData\SaveCurrencyRequest;
use App\Http\Requests\MasterData\SaveDocumentTypeRequest;
use App\Http\Requests\MasterData\SaveEmployeeRequest;
use App\Http\Requests\MasterData\SaveEmploymentTypeRequest;
use App\Http\Requests\MasterData\SavePartnerGroupRequest;
use App\Http\Requests\MasterData\SavePartnerRequest;
use App\Http\Requests\MasterData\SavePartyTypeRequest;
use App\Http\Requests\MasterData\SavePlatformRequest;
use App\Models\MasterData\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Routing\Route;
use Tests\Support\MasterDataFixtures as Form;

$forms = [
    'platform' => [SavePlatformRequest::class, MasterList::Platform, SystemRole::Admin],
    'category' => [SaveCategoryRequest::class, MasterList::Category, SystemRole::Legal],
    'document type' => [SaveDocumentTypeRequest::class, MasterList::DocumentType, SystemRole::Admin],
    'employment type' => [SaveEmploymentTypeRequest::class, MasterList::EmploymentType, SystemRole::Admin],
    'party type' => [SavePartyTypeRequest::class, MasterList::PartyType, SystemRole::Admin],
    'currency' => [SaveCurrencyRequest::class, MasterList::Currency, SystemRole::Admin],
    'partner group' => [SavePartnerGroupRequest::class, MasterList::PartnerGroup, SystemRole::Admin],
    'partner' => [SavePartnerRequest::class, MasterList::Partner, SystemRole::Admin],
];

it('lets the right role save, refuses others with 403, and hides another company with 404', function (string $class, MasterList $list, SystemRole $role) {
    $tenant = Tenant::factory()->create();
    $allowed = User::factory()->role($role)->for($tenant)->create();
    $viewer = User::factory()->role(SystemRole::Viewer)->for($tenant)->create();
    $foreign = User::factory()->role($role)->create();
    $record = Form::row($list, $tenant);

    expect(Form::request($class, $allowed, [])->authorize()->allowed())->toBeTrue()
        ->and(Form::request($class, $allowed, [], $record)->authorize()->allowed())->toBeTrue()
        ->and(Form::request($class, $viewer, [])->authorize()->status())->toBe(403)
        ->and(Form::request($class, $viewer, [], $record)->authorize()->status())->toBe(403)
        ->and(Form::request($class, $foreign, [], $record)->authorize()->status())->toBe(404);
})->with($forms);

it('does the same for the employee form, only for employees.manage', function () {
    $tenant = Tenant::factory()->create();
    $hr = User::factory()->role(SystemRole::HrGa)->for($tenant)->create();
    $legal = User::factory()->role(SystemRole::Legal)->for($tenant)->create();
    $foreignHr = User::factory()->role(SystemRole::HrGa)->create();
    $employee = Employee::factory()->for($tenant)->create();
    $make = fn (User $user, ?Employee $record = null) => tap(SaveEmployeeRequest::create('/x', 'POST', []), function ($request) use ($user, $record) {
        $request->setUserResolver(fn () => $user);
        $record !== null && $request->setRouteResolver(fn () => tap(new Route('PUT', '/x/{record}', []), fn ($route) => $route->bind($request)->setParameter('record', $record)));
    });

    expect($make($hr)->authorize()->allowed())->toBeTrue()
        ->and($make($hr, $employee)->authorize()->allowed())->toBeTrue()
        ->and($make($legal)->authorize()->status())->toBe(403)
        ->and($make($legal, $employee)->authorize()->status())->toBe(403)
        ->and($make($foreignHr, $employee)->authorize()->status())->toBe(404);
});
