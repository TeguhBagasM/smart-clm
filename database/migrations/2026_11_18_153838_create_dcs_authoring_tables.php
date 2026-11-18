<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dcs_version_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->unique()->constrained('dcs_document_versions')->restrictOnDelete();
            $table->text('purpose')->nullable();
            $table->text('scope')->nullable();
            $table->string('language_mode', 10)->default('single');
            $table->string('flow_mode', 10)->default('none');
            $table->string('flow_display_style', 10)->default('diagram');
            $table->jsonb('flow_lanes');
            $table->jsonb('flow_steps');
            $table->string('flow_image_key', 500)->nullable();
            $table->string('flow_image_mime', 100)->nullable();
            $table->jsonb('render_layout')->nullable();
            $table->unsignedInteger('draft_revision')->default(1);
            $table->timestamps();
        });

        Schema::create('dcs_version_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('dcs_document_versions')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('heading', 500);
            $table->text('content');
            $table->jsonb('table_data')->nullable();

            $table->unique(['version_id', 'position']);
        });

        Schema::create('dcs_version_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('dcs_document_versions')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('label', 300);
            $table->text('url');

            $table->unique(['version_id', 'position']);
        });

        Schema::create('dcs_version_clauses', function (Blueprint $table) {
            $table->foreignId('version_id')->constrained('dcs_document_versions')->restrictOnDelete();
            $table->foreignId('clause_id')->constrained('dcs_clauses')->restrictOnDelete();
            $table->unsignedSmallInteger('position');

            $table->primary(['version_id', 'clause_id']);
        });

        Schema::create('dcs_version_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('dcs_document_versions')->restrictOnDelete();
            $table->unsignedSmallInteger('group_position');
            $table->string('group_heading', 100)->nullable();
            $table->unsignedSmallInteger('position');
            $table->string('label', 100);
            $table->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->string('role_name', 100)->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 10)->default('pending');

            $table->unique(['version_id', 'group_position', 'position']);
        });

        Schema::create('dcs_renditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('dcs_document_versions')->restrictOnDelete();
            $table->string('kind', 20);
            $table->unsignedSmallInteger('step_position')->nullable();
            $table->string('language', 5)->nullable();
            $table->string('storage_key', 500);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime_type', 100);
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at', 6)->useCurrent();

            $table->index(['version_id', 'id']);
        });

        Schema::create('dcs_tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->restrictOnDelete();
            $table->string('company_name', 200)->nullable();
            $table->text('company_address')->nullable();
            $table->string('company_logo_key', 500)->nullable();
            $table->string('company_logo_mime', 100)->nullable();
            $table->text('confidentiality_notice')->nullable();
            $table->decimal('page_margin_top_mm', 6, 2)->nullable();
            $table->decimal('page_margin_right_mm', 6, 2)->nullable();
            $table->decimal('page_margin_bottom_mm', 6, 2)->nullable();
            $table->decimal('page_margin_left_mm', 6, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dcs_tenant_settings');
        Schema::dropIfExists('dcs_renditions');
        Schema::dropIfExists('dcs_version_steps');
        Schema::dropIfExists('dcs_version_clauses');
        Schema::dropIfExists('dcs_version_attachments');
        Schema::dropIfExists('dcs_version_sections');
        Schema::dropIfExists('dcs_version_contents');
    }
};
