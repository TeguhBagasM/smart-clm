<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_comparisons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('queued');
            $table->string('stage', 30)->nullable();
            $table->unsignedSmallInteger('progress')->default(0);
            $table->string('label_a', 300);
            $table->string('label_b', 300);
            $table->jsonb('summary')->nullable();
            $table->jsonb('warnings')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at', 6)->nullable();
            $table->timestampTz('finished_at', 6)->nullable();
            $table->timestampsTz(6);

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('document_comparison_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comparison_id')->constrained('document_comparisons')->cascadeOnDelete();
            $table->string('side', 1);
            $table->string('kind', 20);
            $table->foreignId('dcs_version_id')->nullable()->constrained('dcs_document_versions')->nullOnDelete();
            $table->string('file_path', 500)->nullable();
            $table->string('original_name', 300)->nullable();
            $table->string('sha256', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->string('extraction_method', 20)->nullable();
            $table->text('pasted_text')->nullable();
            $table->text('extracted_text')->nullable();
            $table->timestampsTz(6);

            $table->unique(['comparison_id', 'side']);
        });

        Schema::create('document_comparison_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comparison_id')->constrained('document_comparisons')->cascadeOnDelete();
            $table->string('side', 1);
            $table->unsignedInteger('ordinal');
            $table->string('number_label', 60)->nullable();
            $table->string('heading', 500)->nullable();
            $table->text('body');

            $table->unique(['comparison_id', 'side', 'ordinal']);
        });

        Schema::create('document_comparison_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comparison_id')->constrained('document_comparisons')->cascadeOnDelete();
            $table->unsignedInteger('ordinal');
            $table->foreignId('article_a_id')->nullable()->constrained('document_comparison_articles')->nullOnDelete();
            $table->foreignId('article_b_id')->nullable()->constrained('document_comparison_articles')->nullOnDelete();
            $table->string('pairing', 20);
            $table->string('change_kind', 20);
            $table->jsonb('diff')->nullable();
            $table->text('explanation')->nullable();

            $table->unique(['comparison_id', 'ordinal']);
        });

        Schema::create('ai_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('comparison_id')->nullable()->constrained('document_comparisons')->nullOnDelete();
            $table->string('stage', 40);
            $table->string('provider', 20);
            $table->string('model', 80);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('ok')->default(true);
            $table->string('error', 500)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();

            $table->index(['comparison_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_calls');
        Schema::dropIfExists('document_comparison_rows');
        Schema::dropIfExists('document_comparison_articles');
        Schema::dropIfExists('document_comparison_sources');
        Schema::dropIfExists('document_comparisons');
    }
};
