<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make `role_id` mandatory and drop the legacy `role` string column.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'role']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable(false)->change();
            $table->index(['tenant_id', 'role_id']);
        });
    }

    /**
     * Restore the `role` string from the role key. Only lossless while no
     * custom (tenant) role is in use.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'role_id']);
            $table->string('role', 50)->default('viewer')->after('email');
        });

        DB::statement('UPDATE users SET role = (SELECT roles."key" FROM roles WHERE roles.id = users.role_id)');

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->change();
            $table->index(['tenant_id', 'role']);
        });
    }
};
