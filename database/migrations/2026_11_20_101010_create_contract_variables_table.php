<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The fields ({{Code}}) a company's clauses and templates may use (Node
 * `variables`, global in Node; per company here, copied from the defaults,
 * audit P7 and Q-T4). `code` is the placeholder, `name` the plain label people
 * see, `source_field` the contract field that fills it automatically (keys in
 * config/contract_templates.php), empty = typed in when the contract is made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('code', 60);
            $table->string('name', 150);
            $table->string('value_type', 20);
            $table->string('description', 300)->nullable();
            $table->string('default_value', 255)->nullable();
            $table->string('source_field', 60)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->string('legacy_key', 100)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
        });

        // Lookup-list pattern: code and name unique per company; the tenant_id prefix serves the foreign key.
        DB::statement('CREATE UNIQUE INDEX contract_variables_tenant_code_unique ON contract_variables (tenant_id, lower(code))');
        DB::statement('CREATE UNIQUE INDEX contract_variables_tenant_name_unique ON contract_variables (tenant_id, lower(name))');
        DB::statement('CREATE UNIQUE INDEX contract_variables_tenant_legacy_unique ON contract_variables (tenant_id, legacy_key) WHERE legacy_key IS NOT NULL');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE contract_variables ADD CONSTRAINT contract_variables_type_check CHECK (value_type IN ('text', 'number', 'date'))");
            // Only letters, digits and underscore, starting with a letter: a placeholder can never hold code.
            DB::statement("ALTER TABLE contract_variables ADD CONSTRAINT contract_variables_code_check CHECK (code ~ '^[A-Za-z][A-Za-z0-9_]{0,59}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_variables');
    }
};
