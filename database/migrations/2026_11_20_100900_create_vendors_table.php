<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vendors of a company (Node `vendors`, src/types.ts:770-784): the second party
 * of external contracts, with contact, tax and bank details. Separate from
 * partners/operators (audit vendor-template Q-V1). The bank account number is
 * stored encrypted (Q-V4), so it is text and never indexed or searched; the
 * NPWP is kept as digits only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 200);
            $table->string('npwp', 16)->nullable();
            $table->text('address')->nullable();
            $table->string('pic_name', 150)->nullable();
            $table->string('pic_phone', 30)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_branch', 100)->nullable();
            $table->string('bank_account_name', 150)->nullable();
            $table->text('bank_account_number')->nullable();
            $table->decimal('pph_rate_percent', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('legacy_key', 100)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
        });

        // A name appears once per company (Q-V5). The tenant_id prefix serves the foreign key,
        // and the list's default order (lower(name)) reads the page straight from this index.
        DB::statement('CREATE UNIQUE INDEX vendors_tenant_name_unique ON vendors (tenant_id, lower(name))');
        // The importer matches a Node vendor by its id, once per company.
        DB::statement('CREATE UNIQUE INDEX vendors_tenant_legacy_unique ON vendors (tenant_id, legacy_key) WHERE legacy_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
