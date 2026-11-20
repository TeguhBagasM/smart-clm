<?php

use App\Enums\SystemRole;
use App\Models\Dcs\Department;
use App\Models\MasterData\Employee;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->as = fn (SystemRole $role) => $this->actingAs(User::factory()->role($role)->for($this->tenant)->create());
    $this->keys = fn (Assert $page, string $group = 'groups') => collect($page->toArray()['props'][$group])->flatMap(fn ($g) => collect($g['items'])->pluck('key'))->all();
});

it('sends a guest to the login page', function () {
    $this->get(route('master-data.index'))->assertRedirect(route('login'));
});

it('shows each role only the lists it may manage, grouped as the config says', function (SystemRole $role, array $expected) {
    ($this->as)($role)->get(route('master-data.index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('master-data/Index')->where('needs_company', false)->where('companies', null)
            ->tap(fn (Assert $p) => expect(collect($p->toArray()['props']['groups'])->flatMap(fn ($g) => collect($g['items'])->pluck('key'))->all())->toBe($expected)),
    );
})->with([
    'admin' => [SystemRole::Admin, ['categories', 'document-types', 'party-types', 'currencies', 'platforms', 'partner-groups', 'partners', 'employment-types', 'employees']],
    'it_system' => [SystemRole::ItSystem, ['document-types', 'party-types', 'currencies', 'platforms', 'partner-groups', 'partners', 'employment-types']],
    'legal' => [SystemRole::Legal, ['categories']],
    'hr_ga' => [SystemRole::HrGa, ['employees']],
    'viewer' => [SystemRole::Viewer, []],
]);

it('gives each item its label, purpose, link and the count of active rows of the own company only', function () {
    TenantPlatform::factory()->for($this->tenant)->count(2)->create();
    TenantPlatform::factory()->for($this->tenant)->inactive()->create();
    TenantPlatform::factory()->create();
    Employee::factory()->for($this->tenant)->create();
    Department::factory()->for($this->tenant)->create();

    ($this->as)(SystemRole::Admin)->get(route('master-data.index'))->assertInertia(function (Assert $page) {
        $items = collect($page->toArray()['props']['groups'])->flatMap(fn ($g) => $g['items'])->keyBy('key');

        expect($items['platforms'])->toMatchArray(['label' => 'Platform', 'count' => 2, 'href' => '/master-data/platforms', 'description' => config('master_data.lists.platforms.description')])
            ->and($items['employees'])->toMatchArray(['count' => 1, 'href' => '/master-data/employees'])
            ->and($items['currencies']['count'])->toBe(0);
    });
});

it('asks a super admin to pick a company and offers the active ones; with a company it counts that one', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    TenantPlatform::factory()->for($this->tenant)->count(3)->create();
    Tenant::factory()->create(['is_active' => false, 'name' => 'Mati']);

    $this->actingAs($superAdmin)->get(route('master-data.index'))->assertInertia(function (Assert $page) {
        $props = $page->toArray()['props'];
        expect($props['needs_company'])->toBeTrue()->and($props['company'])->toBeNull()
            ->and(collect($props['companies'])->pluck('label'))->not->toContain('Mati')
            ->and(collect($props['groups'])->flatMap(fn ($g) => $g['items'])->pluck('count')->unique()->all())->toBe([null]);
    });

    $this->actingAs($superAdmin)->get(route('master-data.index', ['tenant_id' => $this->tenant->id]))->assertInertia(
        fn (Assert $page) => $page->where('needs_company', false)->where('company.id', $this->tenant->id),
    );
});

it('ignores a company named by a company user', function () {
    $other = Tenant::factory()->create();
    TenantPlatform::factory()->for($other)->count(5)->create();

    ($this->as)(SystemRole::Admin)->get(route('master-data.index', ['tenant_id' => $other->id]))->assertInertia(
        fn (Assert $page) => $page->where('company.id', $this->tenant->id),
    );
});
