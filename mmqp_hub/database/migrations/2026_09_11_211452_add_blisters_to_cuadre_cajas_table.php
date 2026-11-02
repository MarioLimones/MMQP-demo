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
        Schema::table('cuadre_cajas', function (Blueprint $table) {
            $table->json('blisters')->nullable()->after('monedas_ingreso');
            $table->json('blisters_ingreso')->nullable()->after('blisters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuadre_cajas', function (Blueprint $table) {
            $table->dropColumn(['blisters', 'blisters_ingreso']);
        });
    }
};
