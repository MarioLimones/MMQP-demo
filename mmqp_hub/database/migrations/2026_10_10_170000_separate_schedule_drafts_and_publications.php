<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = [];
        foreach (['estado', 'version', 'vigente', 'draft_slot', 'source_publicacion_id', 'published_draft_version', 'published_at', 'last_save_key', 'last_save_hash'] as $column) {
            $missing[$column] = ! Schema::hasColumn('horarios_semanales', $column);
        }

        Schema::table('horarios_semanales', function (Blueprint $table) use ($missing) {
            // Legacy rows without publication evidence remain intact and read-only.
            if ($missing['estado']) $table->string('estado')->default('legacy');
            if ($missing['version']) $table->unsignedInteger('version')->default(1);
            if ($missing['vigente']) $table->boolean('vigente')->default(true);
            if ($missing['draft_slot']) $table->unsignedTinyInteger('draft_slot')->nullable();
            if ($missing['source_publicacion_id']) $table->unsignedBigInteger('source_publicacion_id')->nullable();
            if ($missing['published_draft_version']) $table->unsignedInteger('published_draft_version')->nullable();
            if ($missing['published_at']) $table->timestamp('published_at')->nullable();
            if ($missing['last_save_key']) $table->string('last_save_key', 36)->nullable();
            if ($missing['last_save_hash']) $table->string('last_save_hash', 64)->nullable();
            // NULL slots allow immutable history; only one editable draft per week.
            if ($missing['estado']) {
                $table->unique(['semana_id', 'draft_slot'], 'horario_active_draft_unique');
                $table->index(['semana_id', 'estado', 'vigente']);
            }
        });

        DB::table('horarios_semanales')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $roles = json_decode($row->roles_publicados ?? 'null', true);
                if (is_array($roles) && count($roles) > 0) {
                    DB::table('horarios_semanales')->where('id', $row->id)->update(['estado' => 'published']);
                }
            }
        });
    }

    public function down(): void
    {
        // A rollback would expose new drafts through the old week-only queries.
        if (DB::table('horarios_semanales')->where('estado', 'borrador')->orWhereNotNull('published_at')->exists()) {
            throw new RuntimeException('El protocolo de horarios ya tiene datos nuevos. Exporte sus versiones antes de revertir esta migración.');
        }
        Schema::table('horarios_semanales', function (Blueprint $table) {
            $table->dropUnique('horario_active_draft_unique');
            $table->dropIndex(['semana_id', 'estado', 'vigente']);
            $table->dropColumn(['estado', 'version', 'vigente', 'draft_slot', 'source_publicacion_id',
                'published_draft_version', 'published_at', 'last_save_key', 'last_save_hash']);
        });
    }
};
