<?php

use App\Actions\Rbac\SyncRbacCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed the system roles and permission catalog, then point every user at
     * the system role that matches their legacy `role` key.
     */
    public function up(): void
    {
        app(SyncRbacCatalog::class)->handle();

        DB::statement(<<<'SQL'
            UPDATE users
            SET role_id = (
                SELECT roles.id FROM roles
                WHERE roles."key" = users.role AND roles.tenant_id IS NULL
            )
            WHERE role_id IS NULL
        SQL);

        $unmapped = DB::table('users')->whereNull('role_id')->count();

        if ($unmapped > 0) {
            throw new RuntimeException("{$unmapped} user(s) have a role that matches no system role.");
        }
    }

    /**
     * The data is derived; the schema migration removes the tables.
     */
    public function down(): void
    {
        DB::table('users')->update(['role_id' => null]);
    }
};
