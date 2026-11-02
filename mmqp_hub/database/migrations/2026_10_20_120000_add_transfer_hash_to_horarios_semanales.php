<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horarios_semanales', function (Blueprint $table) {
            $table->string('transfer_hash', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('horarios_semanales', function (Blueprint $table) {
            $table->dropUnique(['transfer_hash']);
            $table->dropColumn('transfer_hash');
        });
    }
};
