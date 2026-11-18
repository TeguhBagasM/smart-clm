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
        Schema::create('dcs_departments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX dcs_departments_tenant_code_unique ON dcs_departments (tenant_id, lower(code))');

        Schema::create('dcs_document_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('layout_style', 20)->default('standard');
            $table->string('header_style', 10)->default('iso');
            $table->boolean('letterhead')->default(false);
            $table->string('document_level', 100)->default('Internal');
            $table->string('process_owner', 150)->default('');
            $table->jsonb('default_lanes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('dcs_document_type_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doc_type_id')->constrained('dcs_document_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('heading', 200);
            $table->text('content');

            $table->unique(['doc_type_id', 'position']);
        });

        Schema::create('dcs_document_type_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doc_type_id')->constrained('dcs_document_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('group_position');
            $table->string('group_heading', 100)->nullable();
            $table->unsignedSmallInteger('position');
            $table->string('label', 100);
            $table->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->string('role_name', 100)->nullable();

            $table->unique(['doc_type_id', 'group_position', 'position']);
        });

        Schema::create('dcs_clauses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('title', 200);
            $table->text('content');
            $table->string('category', 100)->default('General');
            $table->boolean('is_mandatory')->default(false);
            $table->jsonb('tags');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'category']);
        });

        Schema::create('dcs_clause_doc_types', function (Blueprint $table) {
            $table->foreignId('clause_id')->constrained('dcs_clauses')->cascadeOnDelete();
            $table->foreignId('doc_type_id')->constrained('dcs_document_types')->cascadeOnDelete();

            $table->primary(['clause_id', 'doc_type_id']);
        });

        Schema::create('dcs_document_type_default_clauses', function (Blueprint $table) {
            $table->foreignId('doc_type_id')->constrained('dcs_document_types')->cascadeOnDelete();
            $table->foreignId('clause_id')->constrained('dcs_clauses')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');

            $table->primary(['doc_type_id', 'clause_id']);
        });

        Schema::create('dcs_numbering_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('doc_type_id')->nullable()->constrained('dcs_document_types')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('mask', 200);
            $table->jsonb('scope_tokens');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX dcs_numbering_rules_doc_type_unique ON dcs_numbering_rules (tenant_id, doc_type_id) WHERE doc_type_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX dcs_numbering_rules_default_unique ON dcs_numbering_rules (tenant_id) WHERE doc_type_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dcs_numbering_rules');
        Schema::dropIfExists('dcs_document_type_default_clauses');
        Schema::dropIfExists('dcs_clause_doc_types');
        Schema::dropIfExists('dcs_clauses');
        Schema::dropIfExists('dcs_document_type_steps');
        Schema::dropIfExists('dcs_document_type_sections');
        Schema::dropIfExists('dcs_document_types');
        Schema::dropIfExists('dcs_departments');
    }
};
