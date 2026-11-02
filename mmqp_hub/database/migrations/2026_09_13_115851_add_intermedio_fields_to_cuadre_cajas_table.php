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
            $table->decimal('gastos', 10, 2)->default(0)->after('nota_blanca');
            $table->decimal('cambios', 10, 2)->default(0)->after('gastos');
            $table->decimal('repartos', 10, 2)->default(0)->after('cambios');
            $table->decimal('pedidos_hora_exacta', 10, 2)->default(0)->after('repartos');
            $table->decimal('otros', 10, 2)->default(0)->after('pedidos_hora_exacta');
            $table->decimal('venta_parcial', 10, 2)->nullable()->after('otros');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuadre_cajas', function (Blueprint $table) {
            $table->dropColumn([
                'gastos',
                'cambios',
                'repartos',
                'pedidos_hora_exacta',
                'otros',
                'venta_parcial'
            ]);
        });
    }
};
