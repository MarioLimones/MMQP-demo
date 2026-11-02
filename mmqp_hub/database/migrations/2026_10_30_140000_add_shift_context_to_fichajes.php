<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichajes', function (Blueprint $table) {
            // Keep the original shift context even if a schedule is later replaced or deleted.
            $table->unsignedBigInteger('horario_id')->nullable()->index();
            $table->string('turno_id')->nullable();
            $table->dateTime('inicio_previsto')->nullable();
            $table->dateTime('fin_previsto')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fichajes', function (Blueprint $table) {
            $table->dropIndex(['horario_id']);
            $table->dropColumn(['horario_id', 'turno_id', 'inicio_previsto', 'fin_previsto']);
        });
    }
};
