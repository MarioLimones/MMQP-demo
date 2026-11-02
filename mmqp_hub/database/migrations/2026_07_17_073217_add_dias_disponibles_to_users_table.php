<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Columna para días disponibles cuando tipo_disponibilidad = 'dias_especificos'
            // Ej: ["lunes", "domingo"] — el empleado SOLO puede trabajar esos días
            $table->json('dias_disponibles')->nullable()->after('dias_bloqueados_recurrentes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dias_disponibles');
        });
    }
};
