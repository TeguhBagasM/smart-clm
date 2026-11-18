<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dcs_document_versions', function (Blueprint $table) {
            $table->date('last_review_reminder_on')->nullable()->after('review_reminder_days');
        });

        Schema::create('dcs_distributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('version_id')->constrained('dcs_document_versions')->restrictOnDelete();
            $table->unsignedInteger('recipient_count');
            $table->boolean('is_mandatory')->default(true);
            $table->foreignId('distributed_by_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('distributed_at', 6)->useCurrent();

            $table->index('version_id');
        });

        Schema::create('dcs_read_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('version_id')->constrained('dcs_document_versions')->restrictOnDelete();
            $table->foreignId('distribution_id')->nullable()->constrained('dcs_distributions')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 15)->default('pending');
            $table->timestampTz('read_at', 6)->nullable();
            $table->timestampTz('acknowledged_at', 6)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampsTz(6);

            $table->unique(['version_id', 'user_id']);
            $table->index(['version_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dcs_read_receipts');
        Schema::dropIfExists('dcs_distributions');
        Schema::table('dcs_document_versions', function (Blueprint $table) {
            $table->dropColumn('last_review_reminder_on');
        });
    }
};
