<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumo_documents', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->unique();
            $table->string('title');
            $table->json('payload');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('consumo_product_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('consumo_documents')->cascadeOnDelete();
            $table->uuid('product_id');
            $table->json('sources');
            $table->unique(['document_id', 'product_id']);
            $table->timestamps();
        });

        Schema::table('consumo_sheets', function (Blueprint $table) {
            $table->foreignId('document_id')->nullable()->constrained('consumo_documents')->restrictOnDelete();
            $table->unique(['document_id', 'import_id']);
        });
    }

    public function down(): void
    {
        Schema::table('consumo_sheets', function (Blueprint $table) {
            $table->dropUnique(['document_id', 'import_id']);
            $table->dropConstrainedForeignId('document_id');
        });
        Schema::dropIfExists('consumo_product_links');
        Schema::dropIfExists('consumo_documents');
    }
};
