<?php

use App\Enums\SystemRole;
use App\Models\Templates\ContractVariable;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->legal = User::factory()->role(SystemRole::Legal)->for($this->tenant)->create();
    $this->hr = User::factory()->role(SystemRole::HrGa)->for($this->tenant)->create();
});

it('lists the fields of the own company with the source options and the usage', function () {
    ContractVariable::factory()->for($this->tenant)->create(['code' => 'Alamat', 'name' => 'Alamat']);
    ContractVariable::factory()->for($this->other)->create(['code' => 'Asing', 'name' => 'Asing']);

    $this->actingAs($this->legal)->get(route('contract-library.fields.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('contract-library/Fields')
            ->has('items.data', 1)->where('items.data.0.code', 'Alamat')->where('items.data.0.usage_count', 0)
            ->has('info.sources'));
});

it('refuses a user without the right, with 403, and a foreign field with 404', function () {
    $field = ContractVariable::factory()->for($this->tenant)->create();
    $foreign = ContractVariable::factory()->for($this->other)->create();

    $this->actingAs($this->hr)->get(route('contract-library.fields.index'))->assertForbidden();
    $this->actingAs($this->legal)->put(route('contract-library.fields.update', $foreign), ['code' => 'X', 'name' => 'X', 'value_type' => 'text'])->assertNotFound();
    expect($field->refresh()->code)->not->toBe('X');
});

it('adds a field with a toast, and refuses a code that does not fit', function () {
    $this->actingAs($this->legal)->post(route('contract-library.fields.store'), ['code' => 'NamaPerusahaan', 'name' => 'Nama Perusahaan', 'value_type' => 'text', 'source_field' => 'company.name'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Kolom isian Nama Perusahaan ditambahkan.']);

    $this->actingAs($this->legal)->post(route('contract-library.fields.store'), ['code' => 'Nama Perusahaan', 'name' => 'Lain', 'value_type' => 'text'])
        ->assertSessionHasErrors('code');

    $this->actingAs($this->legal)->post(route('contract-library.fields.store'), ['code' => 'Tanggal', 'name' => 'Tanggal', 'value_type' => 'huruf'])
        ->assertSessionHasErrors('value_type');

    expect(ContractVariable::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

it('hides and shows a field without deleting it', function () {
    $field = ContractVariable::factory()->for($this->tenant)->create(['name' => 'Alamat']);

    $this->actingAs($this->legal)->patch(route('contract-library.fields.deactivate', $field))->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Kolom isian Alamat disembunyikan dari pilihan baru. Masih dipakai oleh 0 versi, dan versi itu tetap memakainya.']);
    $this->actingAs($this->legal)->patch(route('contract-library.fields.reactivate', $field))->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Kolom isian Alamat ditampilkan lagi.']);

    expect(ContractVariable::query()->count())->toBe(1)->and($field->refresh()->is_active)->toBeTrue();
});
