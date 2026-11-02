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
            $table->json('monedas_ingreso')->nullable()->after('billetes');
            $table->json('billetes_ingreso')->nullable()->after('monedas_ingreso');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuadre_cajas', function (Blueprint $table) {
            $table->dropColumn(['monedas_ingreso', 'billetes_ingreso']);
        });
    }
};
