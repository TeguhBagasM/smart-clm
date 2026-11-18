<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dcs_document_versions', function (Blueprint $table) {
            $table->unsignedInteger('review_round')->default(1)->after('scheduled_effective_at');
        });

        // One signature per column. A reset (changes requested) voids the rows
        // instead of deleting them, so the history of who signed what survives.
        Schema::create('dcs_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('dcs_document_versions')->restrictOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('column_label', 100);
            $table->foreignId('approver_user_id')->constrained('users')->restrictOnDelete();
            $table->string('approver_role_key', 100);
            $table->char('signed_content_sha256', 64);
            $table->foreignId('rendition_id')->constrained('dcs_renditions')->restrictOnDelete();
            $table->unsignedInteger('review_round');
            $table->timestampTz('decided_at', 6);
            $table->timestampTz('voided_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();

            $table->index(['version_id', 'step_order']);
        });

        DB::statement('CREATE UNIQUE INDEX dcs_one_live_approval_per_step ON dcs_approvals (version_id, step_order) WHERE voided_at IS NULL');

        Schema::create('dcs_review_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('version_id')->constrained('dcs_document_versions')->restrictOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->string('author_name', 150);
            $table->string('author_role_key', 100)->nullable();
            $table->jsonb('anchor');
            $table->text('body');
            $table->string('status', 10)->default('open');
            $table->unsignedInteger('review_round');
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolved_by_name', 150)->nullable();
            $table->timestampTz('resolved_at', 6)->nullable();
            $table->timestampsTz(6);

            $table->index(['version_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dcs_review_comments');
        Schema::dropIfExists('dcs_approvals');
        Schema::table('dcs_document_versions', function (Blueprint $table) {
            $table->dropColumn('review_round');
        });
    }
};
