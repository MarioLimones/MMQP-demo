<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Existing employees retain their weekly availability until explicitly configured.
            $table->string('disponibilidad_festivos', 20)->default('semanal');
        });

        Schema::create('festivos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->string('nombre', 120);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festivos');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('disponibilidad_festivos'));
    }
};
