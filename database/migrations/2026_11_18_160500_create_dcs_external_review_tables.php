<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guests comment without an account, so the author may be a name only.
        Schema::table('dcs_review_comments', function (Blueprint $table) {
            $table->foreignId('author_user_id')->nullable()->change();
        });

        // A review link for outside parties. The token is stored as a hash: the
        // plain token exists only in the link handed out when it is created.
        Schema::create('dcs_external_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('version_id')->constrained('dcs_document_versions')->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at', 6)->nullable();
            $table->boolean('locked')->default(false);
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('disabled_at', 6)->nullable();
            $table->timestampsTz(6);
        });

        DB::statement('CREATE UNIQUE INDEX dcs_one_live_external_review_per_version ON dcs_external_reviews (version_id) WHERE disabled_at IS NULL');

        Schema::create('dcs_external_review_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_review_id')->constrained('dcs_external_reviews')->restrictOnDelete();
            $table->string('name', 80);
            $table->string('note', 300)->nullable();
            $table->timestampTz('approved_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dcs_external_review_approvals');
        Schema::dropIfExists('dcs_external_reviews');
    }
};
