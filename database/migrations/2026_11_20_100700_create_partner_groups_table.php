<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Groups of partners/operators of a company (Node masterData.operatorGroups).
 * Node's list of other companies a group applied to is dropped: a group
 * belongs to one company only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('legacy_key', 150)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
        });

        // The tenant_id prefix also serves the foreign key and the per-company list.
        DB::statement('CREATE UNIQUE INDEX partner_groups_tenant_name_unique ON partner_groups (tenant_id, lower(name))');
        DB::statement('CREATE UNIQUE INDEX partner_groups_tenant_legacy_unique ON partner_groups (tenant_id, legacy_key) WHERE legacy_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_groups');
    }
};
