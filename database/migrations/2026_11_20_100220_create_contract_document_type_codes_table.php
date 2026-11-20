<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra code segments of a document type (Node docTypes[].extraCodes), printed
 * through {Codes} in a contract number, in sort_order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_document_type_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained('contract_document_types')->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('value', 30);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz(6);

            // Serves the foreign key and reading the codes of a type in order.
            $table->index(['document_type_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_document_type_codes');
    }
};
