<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Document types of external and employee contracts (Node masterData.docTypes).
 * Separate from dcs_document_types, which belongs to the DCS module.
 * module is App\Enums\MasterData\DocumentModule; code fills {DocTypeCode}.
 * The template and number format of a type come with their own modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('module', 20)->default('external');
            $table->unsignedSmallInteger('retention_years')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->string('legacy_key', 150)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
        });

        // The tenant_id prefix of the first index also serves the tenant_id foreign key and every per-company list.
        DB::statement('CREATE UNIQUE INDEX contract_document_types_tenant_code_unique ON contract_document_types (tenant_id, lower(code))');
        DB::statement('CREATE UNIQUE INDEX contract_document_types_tenant_name_unique ON contract_document_types (tenant_id, lower(name))');
        DB::statement('CREATE UNIQUE INDEX contract_document_types_tenant_legacy_unique ON contract_document_types (tenant_id, legacy_key) WHERE legacy_key IS NOT NULL');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE contract_document_types ADD CONSTRAINT contract_document_types_module_check CHECK (module IN ('external', 'employee', 'both'))");
            DB::statement('ALTER TABLE contract_document_types ADD CONSTRAINT contract_document_types_retention_check CHECK (retention_years IS NULL OR retention_years > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_document_types');
    }
};
