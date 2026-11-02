<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('en_baja')->default(false)->after('vacaciones');
            $table->date('baja_desde')->nullable()->after('en_baja');
            $table->text('baja_motivo')->nullable()->after('baja_desde');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['en_baja', 'baja_desde', 'baja_motivo']);
        });
    }
};
