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
        Schema::create('dcs_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('rule_id')->constrained('dcs_numbering_rules')->cascadeOnDelete();
            $table->string('scope_hash', 200);
            $table->jsonb('scope_values');
            $table->unsignedBigInteger('current_seq')->default(0);
            $table->timestamps();

            $table->unique(['rule_id', 'scope_hash']);
        });

        Schema::create('dcs_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('document_number', 80);
            $table->foreignId('doc_type_id')->constrained('dcs_document_types')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('dcs_departments')->restrictOnDelete();
            $table->string('title', 500);
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->string('archive_category', 100)->nullable();
            $table->string('archive_sub_folder_id', 100)->nullable();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'document_number']);
            $table->index(['tenant_id', 'doc_type_id', 'department_id']);
        });

        Schema::create('dcs_document_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained('dcs_documents')->restrictOnDelete();
            $table->unsignedInteger('major');
            $table->unsignedInteger('minor');
            $table->string('status', 20)->default('draft');
            $table->text('change_summary')->nullable();
            $table->timestampTz('effective_at', 6)->nullable();
            $table->timestampTz('superseded_at', 6)->nullable();
            $table->timestampTz('review_due_at', 6)->nullable();
            $table->unsignedSmallInteger('review_reminder_days')->default(30);
            $table->timestampTz('scheduled_effective_at', 6)->nullable();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'major', 'minor']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::table('dcs_documents', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('dcs_document_versions')->nullOnDelete();
        });

        DB::statement("CREATE UNIQUE INDEX dcs_one_effective_version_per_document ON dcs_document_versions (document_id) WHERE status = 'effective'");

        Schema::create('dcs_number_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('dcs_number_sequences')->cascadeOnDelete();
            $table->unsignedBigInteger('seq');
            $table->foreignId('document_id')->nullable()->unique()->constrained('dcs_documents')->restrictOnDelete();
            $table->timestampTz('released_at', 6)->nullable();
            $table->timestamps();

            $table->unique(['sequence_id', 'seq']);
            $table->index(['sequence_id', 'released_at']);
        });

        Schema::create('dcs_document_events', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('occurred_at', 6)->useCurrent();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained('dcs_documents')->restrictOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('dcs_document_versions')->restrictOnDelete();
            $table->string('event_type', 60);
            $table->string('actor_type', 10);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 150)->nullable();
            $table->unsignedInteger('review_round')->nullable();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->jsonb('payload')->nullable();
            $table->string('source', 10)->default('live');

            $table->index(['document_id', 'id']);
            $table->index(['version_id', 'id']);
            $table->index(['tenant_id', 'event_type', 'occurred_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE dcs_document_versions ADD CONSTRAINT dcs_version_numbers_positive CHECK (major >= 1 AND minor >= 0)');
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION dcs_document_events_append_only() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'dcs_document_events is append-only';
                END;
                $$ LANGUAGE plpgsql
            SQL);
            DB::unprepared('CREATE TRIGGER dcs_document_events_no_change BEFORE UPDATE OR DELETE ON dcs_document_events FOR EACH ROW EXECUTE FUNCTION dcs_document_events_append_only()');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS dcs_document_events_no_change ON dcs_document_events');
            DB::unprepared('DROP FUNCTION IF EXISTS dcs_document_events_append_only()');
        }

        Schema::dropIfExists('dcs_document_events');
        Schema::dropIfExists('dcs_number_allocations');
        Schema::table('dcs_documents', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('dcs_document_versions');
        Schema::dropIfExists('dcs_documents');
        Schema::dropIfExists('dcs_number_sequences');
    }
};
