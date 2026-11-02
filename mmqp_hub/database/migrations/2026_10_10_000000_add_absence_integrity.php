<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'absence_write_lock')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('absence_write_lock')->default(0);
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DO $$
            DECLARE r RECORD;
            BEGIN
                FOR r IN (
                    SELECT conname
                    FROM pg_constraint con
                    INNER JOIN pg_class rel ON rel.oid = con.conrelid
                    WHERE rel.relname = 'solicitud_ausencias'
                      AND con.contype = 'c'
                      AND pg_get_constraintdef(con.oid) LIKE '%estado%'
                ) LOOP
                    EXECUTE 'ALTER TABLE solicitud_ausencias DROP CONSTRAINT IF EXISTS ' || quote_ident(r.conname);
                END LOOP;
            END $$;");
            DB::statement("ALTER TABLE solicitud_ausencias ADD CONSTRAINT solicitud_ausencias_estado_check CHECK (estado IN ('pendiente', 'aprobada', 'rechazada', 'cancelada'))");
        }

        Schema::table('solicitud_ausencias', function (Blueprint $table) {
            if (DB::getDriverName() !== 'pgsql') {
                $table->enum('estado', ['pendiente', 'aprobada', 'rechazada', 'cancelada'])->default('pendiente')->change();
            }
            if (! Schema::hasColumn('solicitud_ausencias', 'version')) {
                $table->unsignedInteger('version')->default(1);
            }
            if (! Schema::hasColumn('solicitud_ausencias', 'request_key')) {
                $table->string('request_key', 64)->nullable()->unique();
            }
            if (! Schema::hasColumn('solicitud_ausencias', 'request_hash')) {
                $table->string('request_hash', 64)->nullable();
            }
            if (! Schema::hasColumn('solicitud_ausencias', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS solicitud_ausencias_user_id_estado_index ON solicitud_ausencias (user_id, estado)');
        } else {
            try {
                Schema::table('solicitud_ausencias', function (Blueprint $table) {
                    $table->index(['user_id', 'estado']);
                });
            } catch (\Throwable $e) {
                // Índice ya existe
            }
        }

        if (! Schema::hasTable('legacy_availability_imports')) {
            Schema::create('legacy_availability_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->longText('vacaciones_original')->nullable();
                $table->longText('fechas_libres_original')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('availability_blocks')) {
            Schema::create('availability_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('fecha');
                $table->string('source');
                $table->string('status')->default('pending_review');
                $table->text('review_note')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'source', 'fecha']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_blocks');
        Schema::dropIfExists('legacy_availability_imports');
        Schema::table('solicitud_ausencias', function (Blueprint $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS solicitud_ausencias_user_id_estado_index');
            } else {
                $table->dropIndex(['user_id', 'estado']);
            }
            $table->dropUnique(['request_key']);
            $table->dropColumn(['version', 'request_key', 'request_hash', 'deleted_at']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('absence_write_lock'));
        // Keep cancelada in the enum: rollback must not discard cancelled records.
    }
};
