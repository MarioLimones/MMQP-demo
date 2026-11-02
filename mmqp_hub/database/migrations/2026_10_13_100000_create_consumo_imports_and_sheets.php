<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumo_imports', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('original_path');
            $table->json('data');
            $table->unsignedInteger('row_count');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('consumo_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('import_id')->nullable()->constrained('consumo_imports')->restrictOnDelete();
            $table->json('payload');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumo_sheets');
        Schema::dropIfExists('consumo_imports');
    }
};
