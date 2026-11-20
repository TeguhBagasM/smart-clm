<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal employee directory of a company (Node employees), used to prefill
 * employee contracts. No salary (audit P2) and no national id number (audit P3).
 * The department is the DCS one (dcs_departments, read only; audit P4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('employee_number', 50)->nullable();
            $table->string('name', 150);
            $table->string('position', 150)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('dcs_departments')->restrictOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained('employment_types')->restrictOnDelete();
            $table->date('start_date')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('legacy_key', 150)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);

            // Foreign keys used as list filters and for usage counts.
            $table->index('department_id');
            $table->index('employment_type_id');
        });

        // Serves the tenant foreign key and the per-company list sorted by name.
        DB::statement('CREATE INDEX employees_tenant_name_index ON employees (tenant_id, name)');
        DB::statement('CREATE UNIQUE INDEX employees_tenant_number_unique ON employees (tenant_id, lower(employee_number)) WHERE employee_number IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX employees_tenant_legacy_unique ON employees (tenant_id, legacy_key) WHERE legacy_key IS NOT NULL');
        // One employee record per user account; also serves the user_id foreign key.
        DB::statement('CREATE UNIQUE INDEX employees_user_unique ON employees (user_id) WHERE user_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
