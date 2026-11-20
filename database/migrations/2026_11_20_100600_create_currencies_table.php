<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Currencies offered on contracts (Node masterData.currencies).
 * Owned by one company, deactivated instead of deleted; legacy_key holds the
 * Node value it was imported from so a re-import updates instead of duplicating.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('code', 10);
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->string('legacy_key', 150)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
        });

        // The tenant_id prefix of the first index also serves the tenant_id foreign key and every per-company list.
        DB::statement('CREATE UNIQUE INDEX currencies_tenant_code_unique ON currencies (tenant_id, lower(code))');
        DB::statement('CREATE UNIQUE INDEX currencies_tenant_name_unique ON currencies (tenant_id, lower(name))');
        DB::statement('CREATE UNIQUE INDEX currencies_tenant_legacy_unique ON currencies (tenant_id, legacy_key) WHERE legacy_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
