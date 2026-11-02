<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('horarios_semanales', function (Blueprint $table) {
            $table->json('roles_publicados')->nullable()->after('resultados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('horarios_semanales', function (Blueprint $table) {
            $table->dropColumn('roles_publicados');
        });
    }
};
