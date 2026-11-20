<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partners/operators of a group (Node operatorGroups[].operators). Contact and
 * bank details (operatorDetails) are not stored here (audit P6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('partner_group_id')->constrained('partner_groups')->restrictOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('legacy_key', 200)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
        });

        // Serves the group foreign key and the options of a group; a name appears once per group.
        DB::statement('CREATE UNIQUE INDEX partners_group_name_unique ON partners (partner_group_id, lower(name))');
        DB::statement('CREATE UNIQUE INDEX partners_tenant_legacy_unique ON partners (tenant_id, legacy_key) WHERE legacy_key IS NOT NULL');
        // Serves the tenant foreign key and the per-company list sorted by name (a company may hold a thousand partners).
        DB::statement('CREATE INDEX partners_tenant_name_index ON partners (tenant_id, name)');
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
