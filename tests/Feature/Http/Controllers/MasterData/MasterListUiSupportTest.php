<?php

use App\Enums\SystemRole;
use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->role(SystemRole::Admin)->for($this->tenant)->create();
});

it('shows a friendly 403 page with texts from config on list and employee pages', function (string $url) {
    $viewer = User::factory()->role(SystemRole::Viewer)->for($this->tenant)->create();

    $this->actingAs($viewer)->get($url)->assertForbidden()->assertInertia(
        fn (Assert $page) => $page->component('errors/Forbidden')->where('title', config('master_data.messages.forbidden_title'))->where('message', config('master_data.messages.forbidden')),
    );
})->with(['/master-data/platforms', '/master-data/employment-types', '/master-data/employees']);

it('hides the employment types page from a role without that permission but with others', function () {
    $legal = User::factory()->role(SystemRole::Legal)->for($this->tenant)->create();

    $this->actingAs($legal)->get('/master-data/employment-types')->assertForbidden()->assertInertia(fn (Assert $page) => $page->component('errors/Forbidden'));
});

it('sends the texts, empty labels and company choices the screen needs', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Tenant::factory()->create(['name' => 'Mati', 'is_active' => false]);

    $this->actingAs($this->admin)->get('/master-data/partner-groups')->assertInertia(
        fn (Assert $page) => $page->where('companies', null)->where('info.empty.platforms', 'Semua platform')
            ->has('info.texts.impact_used')->has('info.texts.reorder_failed')->has('info.texts.save_failed')->has('info.texts.impact_hide_title'),
    );
    $this->actingAs($superAdmin)->get("/master-data/platforms?tenant_id={$this->tenant->id}")->assertInertia(
        fn (Assert $page) => $page->where('companies', fn ($companies) => collect($companies)->pluck('label')->doesntContain('Mati') && collect($companies)->pluck('value')->contains($this->tenant->id)),
    );
});

it('says whether the order can be set, and sends the rows to order only when asked', function () {
    TenantPlatform::factory()->for($this->tenant)->create(['name' => 'B', 'sort_order' => 2]);
    TenantPlatform::factory()->for($this->tenant)->inactive()->create(['name' => 'A', 'sort_order' => 1]);
    TenantPlatform::factory()->create(['name' => 'Asing']);

    $this->actingAs($this->admin)->get('/master-data/platforms')->assertInertia(
        fn (Assert $page) => $page->where('reorder', ['available' => true, 'hint' => null])->missing('order_rows')
            ->reloadOnly('order_rows', fn (Assert $reload) => $reload->has('order_rows', 2)->where('order_rows.0.name', 'A')->where('order_rows.0.is_active', false)->where('order_rows.1.name', 'B')),
    );
});

it('refuses the order of a list that is too long, with the reason', function () {
    config(['master_data.reorder_max' => 2]);
    TenantPlatform::factory()->for($this->tenant)->count(3)->create();

    $this->actingAs($this->admin)->get('/master-data/platforms')->assertInertia(
        fn (Assert $page) => $page->where('reorder.available', false)->where('reorder.hint', config('master_data.messages.reorder_too_many')),
    );
});

it('orders partners one group at a time', function () {
    $one = PartnerGroup::factory()->for($this->tenant)->create();
    $two = PartnerGroup::factory()->for($this->tenant)->create();
    Partner::factory()->for($one, 'group')->count(2)->create();
    Partner::factory()->for($two, 'group')->create(['name' => 'Di grup dua']);

    $this->actingAs($this->admin)->get('/master-data/partners')->assertInertia(
        fn (Assert $page) => $page->where('reorder.available', false)->where('reorder.hint', config('master_data.messages.reorder_pick_group')),
    );
    $this->actingAs($this->admin)->get("/master-data/partners?filter[group]={$two->id}")->assertInertia(
        fn (Assert $page) => $page->where('reorder.available', true)->reloadOnly('order_rows', fn (Assert $reload) => $reload->has('order_rows', 1)->where('order_rows.0.name', 'Di grup dua')),
    );
});
