<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ids the legacy Node application uses for users and tenants ("usr-legal",
     * "t-01"). Session tokens issued by Node carry them, so the DCS API maps a
     * token to a Laravel user through this column and reports them in payloads.
     */
    public function up(): void
    {
        foreach (['users', 'tenants'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('legacy_id', 64)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'tenants'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique(['legacy_id']);
                $table->dropColumn('legacy_id');
            });
        }
    }
};
