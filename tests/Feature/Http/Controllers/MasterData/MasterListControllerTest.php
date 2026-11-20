<?php

use App\Enums\MasterData\MasterList;
use App\Enums\SystemRole;
use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\MasterDataFixtures;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->role(SystemRole::Admin)->for($this->tenant)->create();
});

$lists = fn () => array_combine(array_column(MasterList::cases(), 'value'), array_map(fn ($l) => [$l], MasterList::cases()));

it('sends a guest to the login page and refuses an unknown list', function () {
    $this->get('/master-data/platforms')->assertRedirect(route('login'));
    $this->actingAs($this->admin)->get('/master-data/nope')->assertNotFound();
});

it('shows the rows of the own company with their usage, can, columns and notice, for every list', function (MasterList $list) {
    $mine = MasterDataFixtures::row($list, $this->tenant);
    MasterDataFixtures::row($list, Tenant::factory()->create());

    $this->actingAs($this->admin)->get(route('master-data.lists.index', $list))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('master-data/List')->has('items.data', 1)->where('items.data.0.id', $mine->getKey())
            ->where('items.data.0.can', ['update' => true, 'deactivate' => true])->has('items.data.0.usage_count')
            ->where('company.id', $this->tenant->id)->where('info.key', $list->value)->where('info.label', $list->label())
            ->has('info.columns')->where('info.usage_covers_documents', false)->where('info.usage_notice', config('master_data.messages.usage_notice'))
            ->has('list.sorts')->has('list.filters'),
    );
})->with($lists);

it('refuses a role without the permission with 403, per list', function (MasterList $list) {
    $viewer = User::factory()->role(SystemRole::Viewer)->for($this->tenant)->create();

    $this->actingAs($viewer)->get(route('master-data.lists.index', $list))->assertForbidden();
})->with($lists);

it('opens categories to legal and platforms only to the settings roles', function () {
    $legal = User::factory()->role(SystemRole::Legal)->for($this->tenant)->create();

    $this->actingAs($legal)->get(route('master-data.lists.index', MasterList::Category))->assertOk();
    $this->actingAs($legal)->get(route('master-data.lists.index', MasterList::Platform))->assertForbidden();
});

it('searches, filters and sorts through the query string and echoes it back', function () {
    TenantPlatform::factory()->for($this->tenant)->create(['name' => 'Tiketux', 'code' => 'TIX', 'sort_order' => 1]);
    TenantPlatform::factory()->for($this->tenant)->create(['name' => 'Asmat', 'code' => 'ASM', 'sort_order' => 2, 'is_active' => false]);

    $names = fn (string $query) => collect($this->actingAs($this->admin)->get("/master-data/platforms{$query}")->viewData('page')['props']['items']['data'])->pluck('name')->all();

    expect($names(''))->toBe(['Tiketux', 'Asmat'])
        ->and($names('?search=tix'))->toBe(['Tiketux'])
        ->and($names('?filter[status]=inactive'))->toBe(['Asmat'])
        ->and($names('?sort=name&direction=asc'))->toBe(['Asmat', 'Tiketux']);

    $this->actingAs($this->admin)->get('/master-data/platforms?search=tix&sort=name&direction=desc&per_page=10')->assertInertia(
        fn (Assert $page) => $page->where('list.search', 'tix')->where('list.sort', 'name')->where('list.direction', 'desc')->where('list.per_page', 10),
    );
});

it('paginates on the server', function () {
    TenantPlatform::factory()->for($this->tenant)->count(12)->create();

    $this->actingAs($this->admin)->get('/master-data/platforms?per_page=10&page=2')->assertInertia(
        fn (Assert $page) => $page->has('items.data', 2)->where('items.meta.current_page', 2)->where('items.meta.total', 12),
    );
});

it('reports how many records use a row, in one pass', function () {
    $platform = TenantPlatform::factory()->for($this->tenant)->create();
    $unused = TenantPlatform::factory()->for($this->tenant)->create(['sort_order' => 99]);
    PartnerGroup::factory()->for($this->tenant)->count(2)->create()->each(fn ($group) => $group->platforms()->attach($platform));

    $this->actingAs($this->admin)->get('/master-data/platforms')->assertInertia(
        fn (Assert $page) => $page->where('items.data.0.usage_count', 2)->where('items.data.1.usage_count', 0),
    );
});

it('gives the form of a list the options it needs', function () {
    ContractCategory::factory()->for($this->tenant)->create(['name' => 'NDA']);
    TenantPlatform::factory()->for($this->tenant)->create(['name' => 'Tiketux']);
    PartnerGroup::factory()->for($this->tenant)->create(['name' => 'Grup A']);

    $options = fn (MasterList $list) => $this->actingAs($this->admin)->get(route('master-data.lists.index', $list))->viewData('page')['props']['options'];

    expect(collect($options(MasterList::DocumentType)['categories'])->pluck('label')->all())->toBe(['NDA'])
        ->and(collect($options(MasterList::DocumentType)['modules'])->pluck('value')->all())->toBe(['external', 'employee', 'both'])
        ->and(collect($options(MasterList::PartnerGroup)['platforms'])->pluck('label')->all())->toBe(['Tiketux'])
        ->and(collect($options(MasterList::Partner)['groups'])->pluck('label')->all())->toBe(['Grup A'])
        ->and($options(MasterList::Platform))->toBe([]);
});

it('lets a super admin pick the company, and sends them back to choose when they have not', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    TenantPlatform::factory()->for($this->tenant)->create(['name' => 'Tiketux']);

    $this->actingAs($superAdmin)->get('/master-data/platforms')->assertRedirect(route('master-data.index'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => config('master_data.messages.choose_company')]);
    $this->actingAs($superAdmin)->get("/master-data/platforms?tenant_id={$this->tenant->id}")->assertInertia(fn (Assert $page) => $page->where('items.data.0.name', 'Tiketux'));
});

it('never shows another company, even when a company user asks for it', function () {
    $other = Tenant::factory()->create();
    TenantPlatform::factory()->for($other)->create(['name' => 'Asing']);

    $this->actingAs($this->admin)->get("/master-data/platforms?tenant_id={$other->id}")->assertInertia(fn (Assert $page) => $page->has('items.data', 0)->where('company.id', $this->tenant->id));
});

it('shows partners with their group name', function () {
    $group = PartnerGroup::factory()->for($this->tenant)->create(['name' => 'Grup A']);
    Partner::factory()->for($group, 'group')->create(['name' => 'Cititrans']);

    $this->actingAs($this->admin)->get('/master-data/partners')->assertInertia(fn (Assert $page) => $page->where('items.data.0.group.name', 'Grup A'));
});

it('saves a new order of the whole list and says so', function () {
    $rows = collect(range(1, 3))->map(fn (int $i) => TenantPlatform::factory()->for($this->tenant)->create(['sort_order' => $i]));
    $new = [$rows[2]->id, $rows[0]->id, $rows[1]->id];

    $this->actingAs($this->admin)->put('/master-data/platforms/order', ['ids' => $new])->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Urutan platform disimpan.']);

    expect($this->tenant->platforms()->orderBy('sort_order')->pluck('id')->all())->toBe($new);
});

it('refuses an order that names a row of another company and changes nothing', function () {
    $mine = TenantPlatform::factory()->for($this->tenant)->create(['sort_order' => 1]);
    $foreign = TenantPlatform::factory()->create(['sort_order' => 9]);

    $this->actingAs($this->admin)->put('/master-data/platforms/order', ['ids' => [$foreign->id, $mine->id]])->assertSessionHasErrors('ids');

    expect($mine->refresh()->sort_order)->toBe(1)->and($foreign->refresh()->sort_order)->toBe(9);
});

it('guards reordering by role, and makes a super admin name the company', function () {
    $platform = TenantPlatform::factory()->for($this->tenant)->create();
    $viewer = User::factory()->role(SystemRole::Viewer)->for($this->tenant)->create();
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($viewer)->put('/master-data/platforms/order', ['ids' => [$platform->id]])->assertForbidden();
    $this->actingAs($superAdmin)->put('/master-data/platforms/order', ['ids' => [$platform->id]])->assertSessionHasErrors('tenant_id');
    $this->actingAs($superAdmin)->put('/master-data/platforms/order', ['ids' => [$platform->id], 'tenant_id' => $this->tenant->id])->assertSessionHasNoErrors();
    $this->put('/master-data/platforms/order', ['ids' => []])->assertSessionHasErrors('ids');
});
