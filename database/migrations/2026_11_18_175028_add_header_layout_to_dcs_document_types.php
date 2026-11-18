<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dcs_document_types', function (Blueprint $table) {
            // Null = the shipped default letterhead (App\Support\Dcs\Header\HeaderLayout::default()).
            $table->jsonb('header_layout')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dcs_document_types', function (Blueprint $table) {
            $table->dropColumn('header_layout');
        });
    }
};
