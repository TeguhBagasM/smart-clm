<?php

use App\Models\Tenant;

it('seeds every active company and skips inactive ones', function () {
    $active = Tenant::factory()->create();
    $inactive = Tenant::factory()->create(['is_active' => false]);

    $this->artisan('master-data:seed-defaults')->assertSuccessful();

    expect($active->currencies()->count())->toBe(count(config('master_data.defaults.currencies')))
        ->and($inactive->currencies()->exists())->toBeFalse();
});

it('writes nothing on a dry run', function () {
    $tenant = Tenant::factory()->create();

    $this->artisan('master-data:seed-defaults', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run, nothing written.')
        ->assertSuccessful();

    expect($tenant->contractCategories()->exists())->toBeFalse();
});

it('limits the run to the given company codes and refuses an unknown one', function () {
    $first = Tenant::factory()->create(['code' => 'AAA']);
    $second = Tenant::factory()->create(['code' => 'BBB']);

    $this->artisan('master-data:seed-defaults', ['--tenant' => ['aaa']])->assertSuccessful();
    $this->artisan('master-data:seed-defaults', ['--tenant' => ['NOPE']])->assertFailed();

    expect($first->partyTypes()->exists())->toBeTrue()
        ->and($second->partyTypes()->exists())->toBeFalse();
});
