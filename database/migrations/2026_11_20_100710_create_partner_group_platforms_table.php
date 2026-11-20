<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platforms a partner group is offered for (Node operatorGroups[].platforms).
 * A group without rows applies to every platform of its company, as in Node.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_group_platforms', function (Blueprint $table) {
            $table->foreignId('partner_group_id')->constrained('partner_groups')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('tenant_platforms')->restrictOnDelete();

            $table->primary(['partner_group_id', 'platform_id']);
            // Serves "groups of a platform" (the partner options of a platform) and its usage count.
            $table->index('platform_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_group_platforms');
    }
};
