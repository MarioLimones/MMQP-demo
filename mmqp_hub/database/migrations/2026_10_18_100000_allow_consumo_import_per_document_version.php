<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumo_sheets', function (Blueprint $table) {
            $table->unsignedInteger('document_version')->nullable();
            $table->dropUnique(['document_id', 'import_id']);
            $table->unique(['document_id', 'import_id', 'document_version'], 'consumo_sheets_document_import_version_unique');
        });
    }

    public function down(): void
    {
        // Never delete historic snapshots merely to restore the former constraint.
        if (DB::table('consumo_sheets')->whereNotNull('document_id')->whereNotNull('import_id')
            ->groupBy('document_id', 'import_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('No se puede revertir esta migración sin perder fichas históricas de un mismo consumo.');
        }
        Schema::table('consumo_sheets', function (Blueprint $table) {
            $table->dropUnique('consumo_sheets_document_import_version_unique');
            $table->unique(['document_id', 'import_id']);
            $table->dropColumn('document_version');
        });
    }
};
