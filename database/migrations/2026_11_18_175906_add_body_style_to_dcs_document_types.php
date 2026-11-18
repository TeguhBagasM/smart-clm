<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dcs_document_types', function (Blueprint $table) {
            $table->string('body_style', 10)->default('standard');
        });
    }

    public function down(): void
    {
        Schema::table('dcs_document_types', function (Blueprint $table) {
            $table->dropColumn('body_style');
        });
    }
};
