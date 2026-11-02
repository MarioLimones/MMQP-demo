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
        Schema::create('cuadre_cajas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('turno', 20); // 'apertura', 'intermedio', 'cierre'
            $table->json('monedas')->nullable();
            $table->json('billetes')->nullable();
            $table->decimal('nota_blanca', 10, 2)->default(0);
            $table->decimal('total_calculado', 10, 2)->default(0);
            $table->decimal('fondo_fijo', 10, 2)->default(300);
            $table->decimal('ingreso_real', 10, 2)->nullable();
            $table->decimal('descuadre', 10, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Un índice único para que no haya duplicados por fecha y turno (opcional, pero buena idea para evitar duplicados accidentales)
            $table->unique(['fecha', 'turno']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuadre_cajas');
    }
};
