<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A contract template of a company (Node `templates`, src/types.ts:124-159):
 * only its identity. The wording lives in contract_template_versions, where at
 * most one version is published (what new contracts use). Never deleted:
 * hidden with is_active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_category_id')->constrained('contract_categories')->restrictOnDelete();
            $table->string('module', 20);
            $table->string('name', 200);
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->string('legacy_key', 100)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
        });

        // A name appears once per company; the tenant_id prefix serves the foreign key and the list order.
        DB::statement('CREATE UNIQUE INDEX contract_templates_tenant_name_unique ON contract_templates (tenant_id, lower(name))');
        // The category foreign key, the category filter and the options of a category in a contract form.
        DB::statement('CREATE INDEX contract_templates_category_index ON contract_templates (contract_category_id)');
        DB::statement('CREATE UNIQUE INDEX contract_templates_tenant_legacy_unique ON contract_templates (tenant_id, legacy_key) WHERE legacy_key IS NOT NULL');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE contract_templates ADD CONSTRAINT contract_templates_module_check CHECK (module IN ('external', 'employee'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};
