<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dcs_header_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('disk_key', 500);
            $table->string('mime', 50);
            $table->unsignedInteger('width_px');
            $table->unsignedInteger('height_px');
            $table->unsignedInteger('size_bytes');
            $table->char('sha256', 64);
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index('tenant_id');
        });

        Schema::table('dcs_tenant_settings', function (Blueprint $table) {
            // The company's saved colours: [{hex, name}], at most 24 (see UpdateBrandColors).
            $table->jsonb('brand_colors')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dcs_tenant_settings', function (Blueprint $table) {
            $table->dropColumn('brand_colors');
        });
        Schema::dropIfExists('dcs_header_assets');
    }
};
