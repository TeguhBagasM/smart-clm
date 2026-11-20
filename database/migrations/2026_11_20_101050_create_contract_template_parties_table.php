<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The parties a template version names, in order (Node Template.parties, e.g.
 * "Pihak Pertama (Perusahaan)"). Frozen with the version on PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_template_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_template_version_id')->constrained('contract_template_versions')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('label', 150);
        });

        // Order inside a version; the prefix serves the version foreign key.
        DB::statement('CREATE UNIQUE INDEX contract_template_parties_position_unique ON contract_template_parties (contract_template_version_id, position)');

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('CREATE TRIGGER contract_template_parties_frozen BEFORE INSERT OR UPDATE OR DELETE ON contract_template_parties FOR EACH ROW EXECUTE FUNCTION contract_template_content_frozen()');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_template_parties');
    }
};
