<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The clause library of a company for external and employee contracts (Node
 * `clauses`, src/types.ts:103-113). Separate from the DCS clauses. The body is
 * structured plain text (paragraphs, lists, **bold**; no HTML, audit Q-T1) with
 * {{Field}} placeholders. Library rows can change (`revision` counts edits);
 * templates keep their own copy, so a change never reaches a published template.
 * Node tags are not kept (Q-T6). Never deleted: hidden with is_active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_clauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_category_id')->constrained('contract_categories')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_protected')->default(false);
            $table->unsignedInteger('revision')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->string('legacy_key', 100)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
        });

        // A title appears once per company; the tenant_id prefix serves the foreign key and the
        // library's default order (by title).
        DB::statement('CREATE UNIQUE INDEX contract_clauses_tenant_title_unique ON contract_clauses (tenant_id, lower(title))');
        // The category foreign key, the category filter of the library and the usage count of a category.
        DB::statement('CREATE INDEX contract_clauses_category_index ON contract_clauses (contract_category_id)');
        DB::statement('CREATE UNIQUE INDEX contract_clauses_tenant_legacy_unique ON contract_clauses (tenant_id, legacy_key) WHERE legacy_key IS NOT NULL');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE contract_clauses ADD CONSTRAINT contract_clauses_revision_check CHECK (revision >= 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_clauses');
    }
};
