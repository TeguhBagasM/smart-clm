<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('key', 60);
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('level');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_assignable')->default(true);
            $table->boolean('is_super_admin')->default(false);
            $table->boolean('is_tenant_admin')->default(false);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
        });

        DB::statement('CREATE UNIQUE INDEX roles_key_global_unique ON roles ("key") WHERE tenant_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX roles_key_tenant_unique ON roles (tenant_id, "key") WHERE tenant_id IS NOT NULL');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE roles ADD CONSTRAINT roles_level_range CHECK (level BETWEEN 0 AND 100)');
            DB::statement('ALTER TABLE roles ADD CONSTRAINT roles_system_is_global CHECK (NOT is_system OR tenant_id IS NULL)');
            DB::statement('ALTER TABLE roles ADD CONSTRAINT roles_super_admin_is_system CHECK (NOT is_super_admin OR is_system)');
            DB::statement('ALTER TABLE roles ADD CONSTRAINT roles_tenant_admin_is_system CHECK (NOT is_tenant_admin OR is_system)');
        }

        Schema::create('permissions', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->timestampTz('deprecated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission_key');
            $table->timestamp('created_at')->nullable();

            $table->primary(['role_id', 'permission_key']);
            $table->foreign('permission_key')->references('key')->on('permissions')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->jsonb('meta')->nullable();

            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('email')->constrained('roles')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
