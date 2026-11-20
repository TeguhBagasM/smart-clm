<?php

use App\Enums\SystemRole;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->tenant = Tenant::factory()->create();
});

it('opens each library list for the right holders, shows the friendly 403 to others, and redirects guests', function (string $route, SystemRole $role, int $status) {
    $user = User::factory()->role($role)->for($this->tenant)->create();

    $this->actingAs($user)->get(route($route))->assertStatus($status);
})->with([
    'clauses legal' => ['contract-library.clauses.index', SystemRole::Legal, 200],
    'clauses hr_ga' => ['contract-library.clauses.index', SystemRole::HrGa, 403],
    'fields legal' => ['contract-library.fields.index', SystemRole::Legal, 200],
    'fields viewer' => ['contract-library.fields.index', SystemRole::Viewer, 403],
    'templates legal' => ['contract-library.templates.index', SystemRole::Legal, 200],
    'templates finance' => ['contract-library.templates.index', SystemRole::Finance, 403],
]);

it('sends a guest to log in on every library page', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['contract-library.clauses.index', 'contract-library.fields.index', 'contract-library.templates.index']);
