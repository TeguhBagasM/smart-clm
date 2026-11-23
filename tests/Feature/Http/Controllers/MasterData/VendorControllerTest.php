<?php

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\MasterData\Vendor;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->legal = User::factory()->role(SystemRole::Legal)->for($this->tenant)->create();
    $this->form = ['name' => 'CV Uji', 'npwp' => '01.234.567.8-901.000', 'pic_name' => 'Pak Uji', 'pic_phone' => '+62 812-0000-1111', 'email' => 'Uji@Vendor.test',
        'bank_name' => 'Bank Uji', 'bank_account_number' => '123-456-7890', 'pph_rate_percent' => '2,5'];
});

it('lists the vendors of the own company with masked account and texts from config, never the account number', function () {
    Vendor::factory()->for($this->tenant)->create(['name' => 'CV Uji', 'npwp' => '012345678901000', 'bank_name' => 'Bank Uji', 'bank_account_number' => '1234567890']);
    Vendor::factory()->for($this->other)->create(['name' => 'Asing']);

    $response = $this->actingAs($this->legal)->get(route('master-data.vendors.index'))->assertOk();
    $json = json_encode($response->viewData('page')['props']['items']);

    $response->assertInertia(fn (Assert $page) => $page->component('master-data/Vendors')->has('items.data', 1)
        ->where('items.data.0.npwp', '01.234.567.8-901.000')->where('items.data.0.bank_account_tail', '7890')->where('items.data.0.usage_count', 0)
        ->where('items.data.0.can', ['update' => true, 'deactivate' => true])->where('info.title', config('vendors.title'))
        ->where('info.columns', config('vendors.columns'))->where('info.usage_notice', config('vendors.messages.usage_notice'))
        ->where('company.id', $this->tenant->id)->where('companies', null));

    expect($json)->not->toContain('1234567890')->not->toContain('Asing')->not->toContain('bank_account_number');
});

it('searches by name or NPWP digits, filters and sorts through the query string', function () {
    Vendor::factory()->for($this->tenant)->create(['name' => 'Beta', 'npwp' => '111111111111111']);
    Vendor::factory()->for($this->tenant)->create(['name' => 'alpha', 'npwp' => null]);
    Vendor::factory()->for($this->tenant)->inactive()->create(['name' => 'Gamma']);
    $names = fn (array $query) => collect($this->actingAs($this->legal)->get(route('master-data.vendors.index', $query))->viewData('page')['props']['items']['data'])->pluck('name')->all();

    expect($names([]))->toBe(['alpha', 'Beta', 'Gamma'])
        ->and($names(['sort' => 'name', 'direction' => 'desc']))->toBe(['Gamma', 'Beta', 'alpha'])
        ->and($names(['search' => '11.111']))->toBe(['Beta'])
        ->and($names(['search' => 'ALP']))->toBe(['alpha'])
        ->and($names(['filter' => ['status' => 'inactive']]))->toBe(['Gamma'])
        ->and($names(['filter' => ['completeness' => 'no_npwp']]))->toBe(['alpha'])
        ->and($names(['filter' => ['status' => 'rusak']]))->toBe(['alpha', 'Beta', 'Gamma']);
});

it('refuses everyone without vendors.manage with 403, on the page and every write', function (SystemRole $role) {
    $actor = User::factory()->role($role)->for($this->tenant)->create();
    $vendor = Vendor::factory()->for($this->tenant)->create();

    $this->actingAs($actor)->get(route('master-data.vendors.index'))->assertForbidden()->assertInertia(fn (Assert $page) => $page->component('errors/Forbidden'));
    $this->actingAs($actor)->post(route('master-data.vendors.store'), ['name' => 'X'])->assertForbidden();
    $this->actingAs($actor)->put(route('master-data.vendors.update', $vendor), ['name' => 'X'])->assertForbidden();
    $this->actingAs($actor)->patch(route('master-data.vendors.deactivate', $vendor))->assertForbidden();
    $this->actingAs($actor)->patch(route('master-data.vendors.reactivate', $vendor))->assertForbidden();
    expect($vendor->refresh()->name)->not->toBe('X')->and($this->tenant->vendors()->count())->toBe(1);
})->with([SystemRole::HrGa, SystemRole::ItSystem, SystemRole::Finance, SystemRole::Viewer]);

it('sends guests to the login page', function () {
    $this->get(route('master-data.vendors.index'))->assertRedirect(route('login'));
});

it('answers 404 for a vendor of another company', function () {
    $foreign = Vendor::factory()->for($this->other)->create();

    $this->actingAs($this->legal)->put(route('master-data.vendors.update', $foreign), ['name' => 'X'])->assertNotFound();
    $this->actingAs($this->legal)->patch(route('master-data.vendors.deactivate', $foreign))->assertNotFound();
    $this->actingAs($this->legal)->patch(route('master-data.vendors.reactivate', $foreign))->assertNotFound();
    expect($foreign->refresh()->only(['name', 'is_active']))->not->toBe(['name' => 'X', 'is_active' => false]);
});

it('adds, changes, hides and shows a vendor with toasts and audit, keeping the account number when left empty', function () {
    $this->actingAs($this->legal)->post(route('master-data.vendors.store'), $this->form)
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Vendor "CV Uji" ditambahkan.']);
    $vendor = $this->tenant->vendors()->sole();

    expect($vendor->only(['npwp', 'email', 'pph_rate_percent', 'bank_account_number']))->toBe(['npwp' => '012345678901000', 'email' => 'uji@vendor.test', 'pph_rate_percent' => '2.50', 'bank_account_number' => '1234567890']);

    $this->actingAs($this->legal)->put(route('master-data.vendors.update', $vendor), ['name' => 'CV Uji Jaya', 'bank_account_number' => ''])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Vendor "CV Uji Jaya" disimpan.']);
    expect($vendor->refresh()->bank_account_number)->toBe('1234567890')->and($vendor->npwp)->toBeNull();

    $this->actingAs($this->legal)->put(route('master-data.vendors.update', $vendor), ['name' => 'CV Uji Jaya', 'clear_bank_account_number' => '1']);
    $this->actingAs($this->legal)->patch(route('master-data.vendors.deactivate', $vendor))->assertInertiaFlash('toast.message', 'Vendor "CV Uji Jaya" disembunyikan dari pilihan kontrak baru.');
    $this->actingAs($this->legal)->patch(route('master-data.vendors.reactivate', $vendor))->assertInertiaFlash('toast.message', 'Vendor "CV Uji Jaya" ditampilkan lagi di pilihan.');

    expect($vendor->refresh()->only(['bank_account_number', 'is_active']))->toBe(['bank_account_number' => null, 'is_active' => true])
        ->and(AuditLog::query()->orderBy('id')->pluck('event')->all())->toBe(['master_data.vendors.created', 'master_data.vendors.updated', 'master_data.vendors.updated', 'master_data.vendors.deactivated', 'master_data.vendors.reactivated']);
});

it('says how to fix each invalid field', function (array $data, string $field, string $message) {
    Vendor::factory()->for($this->tenant)->create(['name' => 'CV Ada']);

    $this->actingAs($this->legal)->post(route('master-data.vendors.store'), $data)->assertSessionHasErrors([$field => $message]);
    expect($this->tenant->vendors()->count())->toBe(1);
})->with([
    'no name' => [['name' => ''], 'name', 'Nama vendor wajib diisi.'],
    'taken name' => [['name' => 'cv ada'], 'name', 'Nama vendor ini sudah ada. Pakai nama lain, atau ubah vendor yang sudah ada.'],
    'short npwp' => [['name' => 'B', 'npwp' => '12.345'], 'npwp', 'NPWP harus 15 atau 16 angka. Periksa lagi angkanya.'],
    'letters in npwp' => [['name' => 'B', 'npwp' => 'abc'], 'npwp', 'NPWP hanya boleh berisi angka, titik, dan tanda hubung.'],
    'bad email' => [['name' => 'B', 'email' => 'bukan-email'], 'email', 'Email belum benar. Contoh: nama@perusahaan.co.id.'],
    'bad phone' => [['name' => 'B', 'pic_phone' => 'telepon'], 'pic_phone', 'Nomor WhatsApp hanya boleh berisi angka (boleh diawali +), misalnya 081234567890.'],
    'rate above 100' => [['name' => 'B', 'pph_rate_percent' => '150'], 'pph_rate_percent', 'Potongan PPh (%) tidak boleh lebih dari 100.'],
    'letters in account' => [['name' => 'B', 'bank_account_number' => '12ab'], 'bank_account_number', 'Nomor rekening hanya boleh berisi angka.'],
]);

it('makes a super admin name the company and keeps a company user in their own', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)->get(route('master-data.vendors.index'))->assertRedirect(route('master-data.index'));
    $this->actingAs($superAdmin)->get(route('master-data.vendors.index', ['tenant_id' => $this->other->id]))->assertInertia(fn (Assert $page) => $page->where('company.id', $this->other->id)->has('companies'));
    $this->actingAs($superAdmin)->post(route('master-data.vendors.store'), ['name' => 'X'])->assertSessionHasErrors('tenant_id');
    $this->actingAs($superAdmin)->post(route('master-data.vendors.store'), ['name' => 'X', 'tenant_id' => $this->other->id])->assertSessionHasNoErrors();
    $this->actingAs($this->legal)->post(route('master-data.vendors.store'), ['name' => 'Y', 'tenant_id' => $this->other->id])->assertSessionHasNoErrors();

    expect($this->other->vendors()->pluck('name')->all())->toBe(['X'])->and($this->tenant->vendors()->pluck('name')->all())->toBe(['Y']);
});

it('offers every user of the company the active vendor names only, as form options', function () {
    Vendor::factory()->for($this->tenant)->create(['name' => 'Aktif', 'npwp' => '012345678901000']);
    Vendor::factory()->for($this->tenant)->inactive()->create(['name' => 'Tersembunyi']);
    Vendor::factory()->for($this->other)->create(['name' => 'Asing']);
    $viewer = User::factory()->role(SystemRole::Viewer)->for($this->tenant)->create();

    $this->actingAs($viewer)->getJson(route('master-data.options', 'vendors'))->assertOk()
        ->assertExactJson(['data' => [['value' => $this->tenant->vendors()->where('name', 'Aktif')->value('id'), 'label' => 'Aktif']]]);
});
