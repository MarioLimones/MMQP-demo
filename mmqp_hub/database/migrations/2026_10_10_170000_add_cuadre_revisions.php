<?php

use App\Services\CuadreLegacyImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite's historic DATE values can contain times. Do not silently merge duplicate logical dates.
        $keys = [];
        foreach (DB::table('cuadre_cajas')->select(['id', 'fecha', 'turno'])->orderBy('id')->cursor() as $record) {
            $key = substr($record->fecha, 0, 10).'|'.$record->turno;
            if (isset($keys[$key])) {
                throw new RuntimeException('Hay cierres antiguos duplicados para '.$key.' (IDs '.$keys[$key].' y '.$record->id.'). Concílialos conservando sus originales antes de migrar.');
            }
            $keys[$key] = $record->id;
        }
        $missing = [];
        foreach (['version', 'calculation_snapshot', 'responsible_id'] as $column) {
            $missing[$column] = ! Schema::hasColumn('cuadre_cajas', $column);
        }
        Schema::table('cuadre_cajas', function (Blueprint $table) use ($missing) {
            if ($missing['version']) {
                $table->unsignedInteger('version')->default(0);
            }
            if ($missing['calculation_snapshot']) {
                $table->json('calculation_snapshot')->nullable();
            }
            if ($missing['responsible_id']) {
                $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });
        if (! Schema::hasTable('cuadre_revisions')) {
            Schema::create('cuadre_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cuadre_id')->constrained('cuadre_cajas')->restrictOnDelete();
                $table->unsignedInteger('version');
                $table->string('origin', 20);
                // No cascading FK: actor identity and its snapshot survive account removal.
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->json('actor_snapshot')->nullable();
                $table->timestamp('recorded_at');
                $table->text('motivo')->nullable();
                $table->uuid('request_id')->nullable()->unique();
                $table->string('request_hash', 64)->nullable();
                $table->json('before_snapshot')->nullable();
                $table->json('snapshot');
                $table->unique(['cuadre_id', 'version']);
            });
        }
        app(CuadreLegacyImporter::class)->run();
    }

    public function down(): void
    {
        throw new RuntimeException('Esta migración conserva auditoría permanente. Para revertir, restaura una copia completa; no borres las revisiones.');
    }
};
