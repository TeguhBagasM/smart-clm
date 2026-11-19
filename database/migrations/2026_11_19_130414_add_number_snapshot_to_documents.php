<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A document keeps the pattern it was numbered with (`number_mask`, `number_tokens`), so
     * editing a pattern later never changes a number already issued, and the number of a new
     * revision is written with the same pattern. `base_number` is the number without its
     * revision, the one to search and refer to; `number_label` is each version's full number.
     * Existing documents were numbered without a revision: both equal their number.
     */
    public function up(): void
    {
        Schema::table('dcs_documents', function (Blueprint $table) {
            $table->string('base_number', 80)->nullable();
            $table->string('number_mask', 200)->nullable();
            $table->jsonb('number_tokens')->nullable();
            $table->index(['tenant_id', 'base_number']);
        });

        Schema::table('dcs_document_versions', function (Blueprint $table) {
            $table->string('number_label', 80)->nullable();
        });

        DB::statement('UPDATE dcs_documents SET base_number = document_number');
        DB::statement('UPDATE dcs_document_versions SET number_label = (SELECT document_number FROM dcs_documents WHERE dcs_documents.id = dcs_document_versions.document_id)');
    }

    public function down(): void
    {
        Schema::table('dcs_document_versions', function (Blueprint $table) {
            $table->dropColumn('number_label');
        });
        Schema::table('dcs_documents', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'base_number']);
            $table->dropColumn(['base_number', 'number_mask', 'number_tokens']);
        });
    }
};
