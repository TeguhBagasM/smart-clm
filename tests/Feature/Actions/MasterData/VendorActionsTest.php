<?php

use App\Actions\MasterData\SaveVendor;
use App\Actions\MasterData\SetVendorActive;
use App\Models\AuditLog;
use App\Models\MasterData\Vendor;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->actor = User::factory()->for($this->tenant)->create();
    $this->save = fn (array $data, ?Vendor $vendor = null) => app(SaveVendor::class)->handle($this->actor, $this->tenant, $data, $vendor);
    $this->full = ['name' => 'CV Uji', 'npwp' => '012345678901000', 'address' => 'Jl. Uji 1', 'pic_name' => 'Pak Uji', 'pic_phone' => '081200001111',
        'email' => 'uji@vendor.test', 'bank_name' => 'Bank Uji', 'bank_branch' => 'Pusat', 'bank_account_name' => 'CV Uji',
        'pph_rate_percent' => '2.00', 'replace_bank_account' => true, 'bank_account_number' => '1234567890'];
});

it('creates a vendor with its details and keeps the account number encrypted at rest', function () {
    $vendor = ($this->save)($this->full);

    $raw = DB::table('vendors')->where('id', $vendor->id)->value('bank_account_number');

    expect($vendor->refresh()->bank_account_number)->toBe('1234567890')
        ->and($raw)->not->toContain('1234567890')
        ->and($vendor->only(['tenant_id', 'is_active', 'created_by_id', 'npwp', 'pph_rate_percent']))
        ->toBe(['tenant_id' => $this->tenant->id, 'is_active' => true, 'created_by_id' => $this->actor->id, 'npwp' => '012345678901000', 'pph_rate_percent' => '2.00'])
        ->and($vendor->bankAccountTail())->toBe('7890')
        ->and($vendor->formattedNpwp())->toBe('01.234.567.8-901.000');
});

it('keeps the stored account number unless the form replaces or clears it', function () {
    $vendor = ($this->save)($this->full);

    ($this->save)(['name' => 'CV Uji', 'replace_bank_account' => false, 'bank_account_number' => null], $vendor);
    expect($vendor->refresh()->bank_account_number)->toBe('1234567890');

    ($this->save)(['name' => 'CV Uji', 'replace_bank_account' => true, 'bank_account_number' => '999988887777'], $vendor);
    expect($vendor->refresh()->bank_account_number)->toBe('999988887777');

    ($this->save)(['name' => 'CV Uji', 'replace_bank_account' => true, 'bank_account_number' => null], $vendor);
    expect($vendor->refresh()->bank_account_number)->toBeNull();
});

it('writes the audit trail without any contact, tax or bank value', function () {
    $vendor = ($this->save)($this->full);
    ($this->save)([...$this->full, 'name' => 'CV Uji Baru', 'bank_account_number' => '555566667777', 'npwp' => '0123456789010001'], $vendor);

    $logs = AuditLog::query()->where('subject_id', $vendor->id)->orderBy('id')->get();
    $json = json_encode($logs->map->only(['before', 'after'])->all());

    expect($logs->pluck('event')->all())->toBe(['master_data.vendors.created', 'master_data.vendors.updated'])
        ->and($logs[1]->before['name'])->toBe('CV Uji')->and($logs[1]->after['name'])->toBe('CV Uji Baru')
        ->and($logs[1]->after['changed_sensitive'])->toEqualCanonicalizing(['npwp', 'bank_account_number'])
        ->and($logs[1]->tenant_id)->toBe($this->tenant->id);

    foreach (['1234567890', '555566667777', '012345678901000', '081200001111', 'uji@vendor.test', 'Pak Uji', 'Bank Uji'] as $secret) {
        expect($json)->not->toContain($secret);
    }
});

it('refuses a name already used in the company, ignoring letter case, but allows it in another company', function () {
    ($this->save)(['name' => 'CV Sama']);
    Vendor::factory()->for($this->other)->create(['name' => 'CV Lain']);

    expect(fn () => ($this->save)(['name' => 'cv sama']))->toThrow(ValidationException::class)
        ->and(($this->save)(['name' => 'CV Lain'])->exists)->toBeTrue()
        ->and(AuditLog::query()->where('event', 'master_data.vendors.created')->count())->toBe(2);
});

it('refuses to change a vendor of another company', function () {
    $foreign = Vendor::factory()->for($this->other)->create();

    expect(fn () => ($this->save)(['name' => 'X'], $foreign))->toThrow(ModelNotFoundException::class)
        ->and($foreign->refresh()->name)->not->toBe('X');
});

it('hides and restores a vendor without deleting, reports usage, and is quiet the second time', function () {
    $vendor = Vendor::factory()->for($this->tenant)->create();
    $set = app(SetVendorActive::class);

    expect($set->handle($this->actor, $vendor, false))->toBe(['usage' => 0, 'changed' => true])
        ->and($set->handle($this->actor, $vendor, false))->toBe(['usage' => 0, 'changed' => false])
        ->and($vendor->refresh()->is_active)->toBeFalse()
        ->and($set->handle($this->actor, $vendor, true)['changed'])->toBeTrue()
        ->and(Vendor::query()->count())->toBe(1)
        ->and(AuditLog::query()->pluck('event')->all())->toBe(['master_data.vendors.deactivated', 'master_data.vendors.reactivated']);
});

it('rolls the change back when the audit entry cannot be written', function () {
    $vendor = Vendor::factory()->for($this->tenant)->create(['name' => 'Asli']);
    DB::statement('DROP TABLE audit_logs');

    expect(fn () => ($this->save)(['name' => 'Baru'], $vendor))->toThrow(Exception::class)
        ->and($vendor->refresh()->name)->toBe('Asli');
});
