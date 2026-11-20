<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\Employee;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_number' => null,
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'department_id' => null,
            'employment_type_id' => null,
            'start_date' => fake()->date(),
            'user_id' => null,
            'is_active' => true,
        ];
    }

    /**
     * A row hidden from new choices.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
