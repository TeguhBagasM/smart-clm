<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versions of a contract template (audit Q-T2). A draft is edited freely (with
 * `draft_revision` for optimistic saves); a published version is frozen and is
 * what new contracts use; publishing the next one retires it. At most one draft
 * per template. On PostgreSQL a trigger refuses any change to the wording of a
 * version that is no longer a draft, and the deletion of such a version; a
 * second function, used by the clause, party and field tables, refuses changes
 * to the content of a frozen version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_template_id')->constrained('contract_templates')->restrictOnDelete();
            $table->unsignedSmallInteger('version_no');
            $table->string('status', 20)->default('draft');
            $table->text('opening_paragraph')->nullable();
            $table->text('closing_paragraph')->nullable();
            $table->text('addendum_recital')->nullable();
            $table->unsignedInteger('draft_revision')->default(1);
            $table->timestampTz('published_at', 6)->nullable();
            $table->foreignId('published_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('retired_at', 6)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
        });

        // Version numbers of a template; the prefix serves the template foreign key and the version history.
        DB::statement('CREATE UNIQUE INDEX contract_template_versions_number_unique ON contract_template_versions (contract_template_id, version_no)');
        // One draft per template at a time.
        DB::statement("CREATE UNIQUE INDEX contract_template_versions_one_draft ON contract_template_versions (contract_template_id) WHERE status = 'draft'");
        // One published version per template: the one new contracts use (the template's current version).
        DB::statement("CREATE UNIQUE INDEX contract_template_versions_one_published ON contract_template_versions (contract_template_id) WHERE status = 'published'");

        if (DB::getDriverName() === 'pgsql') {
            $this->guards();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS contract_template_versions_frozen ON contract_template_versions');
            DB::unprepared('DROP FUNCTION IF EXISTS contract_template_versions_frozen()');
            DB::unprepared('DROP FUNCTION IF EXISTS contract_template_content_frozen()');
        }

        Schema::dropIfExists('contract_template_versions');
    }

    private function guards(): void
    {
        DB::statement("ALTER TABLE contract_template_versions ADD CONSTRAINT contract_template_versions_status_check CHECK (status IN ('draft', 'published', 'retired'))");
        DB::statement("ALTER TABLE contract_template_versions ADD CONSTRAINT contract_template_versions_published_check CHECK (status = 'draft' OR published_at IS NOT NULL)");
        DB::statement('ALTER TABLE contract_template_versions ADD CONSTRAINT contract_template_versions_number_check CHECK (version_no >= 1)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION contract_template_versions_frozen() RETURNS trigger AS $$
            BEGIN
                IF OLD.status = 'draft' THEN
                    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                    RETURN NEW;
                END IF;
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'contract template version % is no longer a draft and cannot be deleted', OLD.id;
                END IF;
                IF NEW.contract_template_id <> OLD.contract_template_id OR NEW.version_no <> OLD.version_no
                    OR NEW.opening_paragraph IS DISTINCT FROM OLD.opening_paragraph
                    OR NEW.closing_paragraph IS DISTINCT FROM OLD.closing_paragraph
                    OR NEW.addendum_recital IS DISTINCT FROM OLD.addendum_recital
                    OR NEW.published_at IS DISTINCT FROM OLD.published_at
                    OR NOT (NEW.status = OLD.status OR (OLD.status = 'published' AND NEW.status = 'retired')) THEN
                    RAISE EXCEPTION 'contract template version % is frozen', OLD.id;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::unprepared('CREATE TRIGGER contract_template_versions_frozen BEFORE UPDATE OR DELETE ON contract_template_versions FOR EACH ROW EXECUTE FUNCTION contract_template_versions_frozen()');

        // Used by contract_template_clauses, _parties and _version_variables. A row whose version
        // is gone (a draft being deleted, cascading) passes; the version trigger guards that.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION contract_template_content_frozen() RETURNS trigger AS $$
            DECLARE version_status text;
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    SELECT status INTO version_status FROM contract_template_versions WHERE id = OLD.contract_template_version_id;
                    IF version_status IS NOT NULL AND version_status <> 'draft' THEN
                        RAISE EXCEPTION 'contract template version % is frozen', OLD.contract_template_version_id;
                    END IF;
                END IF;
                IF TG_OP <> 'DELETE' THEN
                    SELECT status INTO version_status FROM contract_template_versions WHERE id = NEW.contract_template_version_id;
                    IF version_status IS DISTINCT FROM 'draft' THEN
                        RAISE EXCEPTION 'contract template version % is frozen', NEW.contract_template_version_id;
                    END IF;
                    RETURN NEW;
                END IF;
                RETURN OLD;
            END;
            $$ LANGUAGE plpgsql
        SQL);
    }
};
