<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The clauses of a template version, in order: a copy of the library clause at
 * the time it was added (audit Q-T2), so editing the library never changes a
 * version. `source_clause_id`/`source_revision` say where the copy came from,
 * so a draft can show "the library clause was updated". Frozen with the
 * version on PostgreSQL (contract_template_content_frozen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_template_clauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_template_version_id')->constrained('contract_template_versions')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('source_clause_id')->nullable()->constrained('contract_clauses')->nullOnDelete();
            $table->unsignedInteger('source_revision')->nullable();
            $table->string('title', 200);
            $table->text('body');
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_protected')->default(false);
        });

        // Order inside a version; the prefix serves the version foreign key (reading a version's clauses).
        DB::statement('CREATE UNIQUE INDEX contract_template_clauses_position_unique ON contract_template_clauses (contract_template_version_id, position)');
        // The source foreign key and the usage count of a library clause ("used in N templates").
        DB::statement('CREATE INDEX contract_template_clauses_source_index ON contract_template_clauses (source_clause_id)');

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('CREATE TRIGGER contract_template_clauses_frozen BEFORE INSERT OR UPDATE OR DELETE ON contract_template_clauses FOR EACH ROW EXECUTE FUNCTION contract_template_content_frozen()');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_template_clauses');
    }
};
