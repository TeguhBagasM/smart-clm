<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The fields a template version asks for when a contract is made (Node
 * Template.requiredVariables), in order, each required or optional. A field in
 * use cannot be removed from the company's list (restrict). Frozen with the
 * version on PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_template_version_variables', function (Blueprint $table) {
            $table->foreignId('contract_template_version_id')->constrained('contract_template_versions')->cascadeOnDelete();
            $table->foreignId('contract_variable_id')->constrained('contract_variables')->restrictOnDelete();
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('position');
            $table->primary(['contract_template_version_id', 'contract_variable_id']);
        });

        // The field foreign key, and the usage count of a field ("used in N templates").
        DB::statement('CREATE INDEX contract_template_version_variables_variable_index ON contract_template_version_variables (contract_variable_id)');

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('CREATE TRIGGER contract_template_version_variables_frozen BEFORE INSERT OR UPDATE OR DELETE ON contract_template_version_variables FOR EACH ROW EXECUTE FUNCTION contract_template_content_frozen()');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_template_version_variables');
    }
};
