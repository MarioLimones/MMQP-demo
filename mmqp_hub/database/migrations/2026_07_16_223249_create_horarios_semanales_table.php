<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios_semanales', function (Blueprint $table) {
            $table->id();
            $table->string('semana_id');           // e.g. "2026-W29"
            $table->string('fecha_inicio');         // e.g. "13 jul"
            $table->string('fecha_fin');            // e.g. "19 jul"
            $table->json('plantilla_diseno');       // turnos requeridos por día
            $table->json('resultados');             // asignaciones turno → empleado
            $table->json('empleados_snapshot');     // snapshot de empleados al generar
            $table->integer('turnos_cubiertos')->default(0);
            $table->integer('turnos_totales')->default(0);
            $table->foreignId('generado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios_semanales');
    }
};
