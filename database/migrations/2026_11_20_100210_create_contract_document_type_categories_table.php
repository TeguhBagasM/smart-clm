<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories a document type is offered for (Node docTypes[].categories).
 * A type without rows is offered for every category, as in Node.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_document_type_categories', function (Blueprint $table) {
            $table->foreignId('document_type_id')->constrained('contract_document_types')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('contract_categories')->restrictOnDelete();

            $table->primary(['document_type_id', 'category_id']);
            // The primary key serves lookups by type; this one serves "types of a category" and its usage count.
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_document_type_categories');
    }
};
