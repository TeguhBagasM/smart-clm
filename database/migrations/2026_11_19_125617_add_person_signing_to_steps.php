<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every signature column that exists today becomes "legacy": it keeps the old
     * rule (a named person, else any holder of the role) until an administrator
     * names a person. Nobody is picked automatically.
     */
    public function up(): void
    {
        Schema::table('dcs_document_type_steps', function (Blueprint $table) {
            $table->string('fill_mode', 20)->default('legacy');
            $table->foreignId('default_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('allow_creator')->default(false);
        });

        Schema::table('dcs_version_steps', function (Blueprint $table) {
            $table->string('fill_mode', 20)->default('legacy');
            $table->boolean('allow_creator')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('dcs_version_steps', function (Blueprint $table) {
            $table->dropColumn(['fill_mode', 'allow_creator']);
        });
        Schema::table('dcs_document_type_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_user_id');
            $table->dropColumn(['fill_mode', 'allow_creator']);
        });
    }
};
