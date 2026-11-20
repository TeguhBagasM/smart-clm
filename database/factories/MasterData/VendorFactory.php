<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\Vendor;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->company(),
            'npwp' => fake()->numerify(str_repeat('#', 15)),
            'address' => fake()->address(),
            'pic_name' => fake()->name(),
            'pic_phone' => fake()->numerify('08##########'),
            'email' => fake()->safeEmail(),
            'bank_name' => 'Bank Uji',
            'bank_branch' => 'Cabang Uji',
            'bank_account_name' => fake()->company(),
            'bank_account_number' => fake()->numerify(str_repeat('#', 12)),
            'pph_rate_percent' => '2.00',
            'is_active' => true,
        ];
    }

    /**
     * A vendor hidden from new choices.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
