<?php

use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Enums\SystemRole;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\NumberingRule;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;

it('creates the five default types and the default numbering rule', function () {
    $tenant = Tenant::factory()->create();

    app(SeedDcsTenantDefaults::class)->handle($tenant);

    expect($tenant->dcsDocumentTypes()->orderBy('id')->pluck('code')->all())->toBe(['SOP', 'IK', 'POL', 'MEMO', 'STD'])
        ->and(NumberingRule::query()->where('tenant_id', $tenant->id)->whereNull('doc_type_id')->value('mask'))->toBe('{DocType}/{Department}/{Year}/{Sequence:4}');
});

it('points the default signer steps at system roles', function () {
    $tenant = Tenant::factory()->create();

    app(SeedDcsTenantDefaults::class)->handle($tenant);

    $sop = DocumentType::query()->where('tenant_id', $tenant->id)->where('code', 'SOP')->sole();
    expect($sop->steps()->with('role')->get()->map(fn ($step) => [$step->label, $step->role->key, $step->role->is_system])->all())->toBe([
        ['Penyusun', 'staff', true], ['Mengetahui', 'manajemen', true], ['Menyetujui', 'legal', true],
    ]);
});

it('is idempotent and never overwrites what a tenant changed', function () {
    $tenant = Tenant::factory()->create();
    app(SeedDcsTenantDefaults::class)->handle($tenant);
    $sop = DocumentType::query()->where('tenant_id', $tenant->id)->where('code', 'SOP')->sole();
    $sop->forceFill(['name' => 'SOP Kustom'])->save();
    $sop->sections()->delete();

    app(SeedDcsTenantDefaults::class)->handle($tenant);

    expect($tenant->dcsDocumentTypes()->count())->toBe(5)
        ->and($sop->fresh()->name)->toBe('SOP Kustom')
        ->and($sop->sections()->count())->toBe(0)
        ->and(NumberingRule::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('seeds each tenant independently', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    app(SeedDcsTenantDefaults::class)->handle($a);

    expect($b->dcsDocumentTypes()->count())->toBe(0);
});

it('can be run for every active tenant with dcs:seed-defaults', function () {
    $active = Tenant::factory()->create();
    $inactive = Tenant::factory()->inactive()->create();

    $this->artisan('dcs:seed-defaults')->assertSuccessful();

    expect($active->dcsDocumentTypes()->count())->toBe(5)
        ->and($inactive->dcsDocumentTypes()->count())->toBe(0);
});

it('stops a role that signers depend on from being deleted', function () {
    $tenant = Tenant::factory()->create();
    $role = Role::factory()->for($tenant)->create();
    $type = DocumentType::factory()->for($tenant)->create();
    DB::table('dcs_document_type_steps')->insert(['doc_type_id' => $type->id, 'group_position' => 1, 'position' => 1, 'label' => 'Tanda tangan', 'role_id' => $role->id]);
    $admin = User::factory()->for($tenant)->role(SystemRole::Admin)->create();

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))->assertSessionHasErrors('role');

    expect(Role::query()->find($role->id))->not->toBeNull();
});
