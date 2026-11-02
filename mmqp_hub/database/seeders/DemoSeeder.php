<?php

namespace Database\Seeders;

use App\Models\ConsumoDocument;
use App\Models\ConsumoImport;
use App\Models\ConsumoSheet;
use App\Models\Festivo;
use App\Models\Fichaje;
use App\Models\HorarioSemanal;
use App\Models\PlantillaHorario;
use App\Models\User;
use App\Services\AbsenceService;
use App\Services\ConsumoGrid;
use App\Services\ConsumoHistory;
use App\Services\ConsumoSheetSnapshot;
use App\Services\EmployeeAvailability;
use App\Services\SaveCuadre;
use App\Support\ArqueoDocument;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Entirely invented examples. This class never reads a local database or export. */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'DemoMMQP!2026';

    public function run(): void
    {
        if (! config('demo.enabled')) {
            throw new RuntimeException('La carga de ejemplos requiere DEMO_MODE=true.');
        }
        if (DB::table('mmqp_demo_state')->where('key', 'seed_version')->exists()) {
            $this->command?->info('La demo ya está inicializada; se conservan los cambios de prueba.');

            return;
        }
        // Never merge fabricated examples into another installation's records.
        foreach (['users', 'plantilla_horarios', 'horarios_semanales', 'solicitud_ausencias',
            'fichajes', 'festivos', 'consumo_documents', 'consumo_imports', 'consumo_sheets',
            'consumo_records', 'cuadre_cajas', 'cuadre_revisions', 'availability_blocks',
            'legacy_availability_imports'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('La demo solo se inicializa sobre una base de datos vacía.');
            }
        }
        if (File::exists(storage_path('app/arqueo_template.json')) || File::isDirectory(storage_path('app/arqueos'))) {
            throw new RuntimeException('La demo requiere almacenamiento de arqueo vacío y propio.');
        }

        $today = CarbonImmutable::now('Europe/Madrid')->startOfDay();
        DB::transaction(function () use ($today) {
            $users = $this->users($today);
            $admin = $users->firstWhere('email', 'admin@mmqp.example');
            $this->absences($users, $admin, $today);
            $this->schedules($users, $admin, $today);
            $this->clockings($users, $today);
            $this->consumption($admin, $today);
            $this->cash($users, $admin, $today);
            $this->arqueo($users, $today);
            DB::table('mmqp_demo_state')->insert([
                'key' => 'seed_version', 'value' => '1',
                'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            DB::table('mmqp_demo_state')->insert([
                'key' => 'reference_date', 'value' => $today->toDateString(),
                'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
        });
        $this->command?->info('Demo creada con ejemplos ficticios y fechas relativas al día de instalación.');
    }

    private function users(CarbonImmutable $today): Collection
    {
        $profiles = [
            ['Administración', 'admin', 'admin', true],
            ['Oficina', 'oficina', 'oficina', true],
            ['Encargado 01', 'encargado', 'empleado', false],
            ['Encargada 02', 'encargado', 'encargada02', false],
            ['Encargado 03', 'encargado', 'encargado03', false],
            ['Repartidor 01', 'repartidor', 'repartidor01', false],
            ['Repartidora 02', 'repartidor', 'repartidora02', false],
            ['Repartidor 03', 'repartidor', 'repartidor03', false],
            ['Repartidora 04', 'repartidor', 'repartidora04', false],
            ['Auxiliar 01', 'auxiliar', 'auxiliar01', false],
            ['Auxiliar 02', 'auxiliar', 'auxiliar02', false],
            ['Limpieza 01', 'limpiadora', 'limpieza01', false],
            ['Baja de ejemplo', 'repartidor', 'baja', false],
            ['Perfil inactivo', 'auxiliar', 'inactivo', false],
        ];
        $password = Hash::make(self::PASSWORD);

        return collect($profiles)->map(function (array $profile, int $index) use ($today, $password) {
            [$name, $role, $email, $isAdmin] = $profile;
            $user = User::create([
                'nombre' => $name, 'apellidos' => 'Demo', 'rol' => $role,
                'email' => $email.'@mmqp.example', 'password' => $password,
                // Deliberately incorrect DNI checksum letters; numbers also serve as demo PINs.
                'dni' => $role === 'admin' ? null : str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT).($index === 8 ? 'X' : 'D'),
                'telefono' => null, 'es_admin' => $isAdmin, 'activo' => $email !== 'inactivo',
                'en_baja' => $email === 'baja', 'es_menor' => false,
                'horas_mensuales' => $role === 'admin' ? null : ($role === 'oficina' ? 160 : ($role === 'limpiadora' ? 40 : 80)),
                'tipo_disponibilidad' => 'completa', 'disponibilidad_festivos' => 'semanal',
                'dias_bloqueados_recurrentes' => $role === 'oficina' ? ['sabado', 'domingo'] : [],
                'dias_disponibles' => [], 'orden_visual' => $index,
                'fecha_inicio' => $today->subMonths(6)->toDateString(),
                'fecha_fin_contrato' => $email === 'inactivo' ? $today->subDay()->toDateString() : null,
            ]);
            $user->forceFill(['email_verified_at' => now('UTC'), 'vacaciones' => [], 'fechas_libres_solicitadas' => []])->save();

            return $user;
        });
    }

    private function absences(Collection $users, User $admin, CarbonImmutable $today): void
    {
        $service = app(AbsenceService::class);
        foreach ([
            ['repartidora04', 'vacaciones', 14, 18],
            ['auxiliar02', 'dia_libre', 4, 4],
            ['baja', 'baja', -3, 8],
        ] as [$email, $type, $start, $end]) {
            $service->create($admin, [
                'user_id' => $users->firstWhere('email', $email.'@mmqp.example')->id,
                'fecha' => $today->addDays($start)->toDateString(), 'fecha_fin' => $today->addDays($end)->toDateString(),
                'tipo' => $type, 'motivo_empleado' => 'Ejemplo ficticio para explorar el calendario.',
                'request_key' => (string) Str::uuid(),
            ]);
        }
        $employee = $users->firstWhere('email', 'empleado@mmqp.example');
        $service->create($employee, [
            'fecha' => $today->addDays(10)->toDateString(),
            'motivo_empleado' => 'Solicitud de prueba pendiente de revisión.', 'request_key' => (string) Str::uuid(),
        ], true);
        Festivo::create(['fecha' => $today->addDays(21)->toDateString(), 'nombre' => 'Festivo ficticio de demostración']);
    }

    private function schedules(Collection $users, User $admin, CarbonImmutable $today): void
    {
        $monday = $today->startOfWeek();
        foreach ([-1, 0, 1] as $offset) {
            $start = $monday->addWeeks($offset);
            [$layout, $assignments] = $this->weeklyLayout($users, $start);
            $isDraft = $offset === 1;
            HorarioSemanal::create([
                'semana_id' => $start->format('o-\WW'),
                'fecha_inicio' => $start->toDateString(), 'fecha_fin' => $start->addDays(6)->toDateString(),
                'plantilla_diseno' => $layout, 'resultados' => $assignments,
                'empleados_snapshot' => $users->filter(fn ($user) => $user->isEmployee())->map(fn ($user) => [
                    'id' => $user->id, 'nombre' => $user->nombre, 'apellidos' => $user->apellidos,
                    'rol' => $user->rol, 'horas_mensuales' => $user->horas_mensuales,
                    'activo' => $user->activo, 'en_baja' => $user->en_baja,
                    'tipo_disponibilidad' => $user->tipo_disponibilidad,
                    'disponibilidad_festivos' => $user->disponibilidad_festivos,
                    'dias_disponibles' => $user->dias_disponibles,
                    'dias_bloqueados_recurrentes' => $user->dias_bloqueados_recurrentes,
                    'vacaciones' => $user->vacaciones, 'fechas_libres_solicitadas' => $user->fechas_libres_solicitadas,
                ])->values()->all(),
                'turnos_cubiertos' => count($assignments), 'turnos_totales' => count($assignments),
                'generado_por' => $admin->id, 'estado' => $isDraft ? 'borrador' : 'published',
                'version' => 1, 'vigente' => ! $isDraft, 'draft_slot' => $isDraft ? 1 : null,
                'roles_publicados' => $isDraft ? [] : User::EMPLOYEE_ROLES,
                'published_at' => $isDraft ? null : $start->subDay()->utc(),
            ]);
        }
        // Templates are reusable day layouts; no employee assignment or historical date.
        PlantillaHorario::create(['nombre' => 'DEMO · Día de ejemplo', 'turnos' => [
            ['rol' => 'encargado', 'puesto_num' => 1, 'hora_inicio' => '12:00', 'hora_fin' => '16:00', 'empleado_id' => null],
            ['rol' => 'repartidor', 'puesto_num' => 1, 'hora_inicio' => '12:00', 'hora_fin' => '16:00', 'empleado_id' => null],
            ['rol' => 'encargado', 'puesto_num' => 2, 'hora_inicio' => '19:00', 'hora_fin' => '23:00', 'empleado_id' => null],
            ['rol' => 'repartidor', 'puesto_num' => 2, 'hora_inicio' => '19:00', 'hora_fin' => '23:00', 'empleado_id' => null],
            ['rol' => 'auxiliar', 'puesto_num' => 1, 'hora_inicio' => '18:00', 'hora_fin' => '22:00', 'empleado_id' => null],
        ]]);
    }

    private function weeklyLayout(Collection $users, CarbonImmutable $start): array
    {
        $days = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        $layout = $assignments = [];
        foreach ($days as $index => $day) {
            $date = $start->addDays($index)->toDateString();
            $specifications = [
                ['encargado', '12:00', '16:00', $index % 3],
                ['encargado', '19:00', '23:00', ($index + 1) % 3],
                ['repartidor', '12:00', '16:00', $index % 4],
                ['repartidor', '19:00', '23:00', ($index + 2) % 4],
                ['auxiliar', '18:00', '22:00', $index % 2],
            ];
            if ($index < 5) $specifications[] = ['oficina', '09:00', '17:00', 0];
            if (in_array($index, [0, 2, 4], true)) $specifications[] = ['limpiadora', '09:00', '12:00', 0];
            $shifts = [];
            $used = [];
            foreach ($specifications as $position => [$role, $from, $to, $rotation]) {
                $candidates = $users->where('rol', $role)->where('activo', true)->where('en_baja', false)->values();
                $rotated = $candidates->slice($rotation)->concat($candidates->take($rotation));
                $employee = $rotated->first(fn ($user) => ! in_array($user->id, $used, true)
                    && app(EmployeeAvailability::class)->isAvailable($user->fresh(), $date, $from < '16:00' ? 'mañana' : 'tarde'));
                if (! $employee) continue;
                $used[] = $employee->id;
                $id = 'demo-'.$date.'-'.$position;
                $duration = (int) substr($to, 0, 2) - (int) substr($from, 0, 2);
                $shift = ['turno_id' => $id, '_idLocal' => $id, 'puesto_num' => $position + 1,
                    'rol_necesario' => $role, 'hora_inicio' => $from, 'hora_fin' => $to,
                    'duracion_horas' => $duration, 'empleado_id' => $employee->id,
                    'empleado_nombre' => $employee->nombre.' Demo'];
                $shifts[] = $shift;
                $assignments[] = ['turno_id' => $id, 'empleado_id' => $employee->id,
                    'nombre' => $employee->nombre.' Demo', 'hora_inicio' => $from, 'hora_fin' => $to,
                    'duracion_horas' => $duration, 'dia' => $day, 'rol' => $role];
            }
            $layout[] = ['dia' => $day, 'fecha' => $date, 'notas' => 'Horario ficticio de demostración.', 'turnos_requeridos' => $shifts];
        }

        return [$layout, $assignments];
    }

    private function clockings(Collection $users, CarbonImmutable $today): void
    {
        foreach (HorarioSemanal::where('estado', 'published')->get() as $schedule) {
            foreach ($schedule->plantilla_diseno as $day) {
                if ($day['fecha'] >= $today->toDateString()) continue;
                foreach ($day['turnos_requeridos'] as $shift) {
                    Fichaje::create([
                        'user_id' => $shift['empleado_id'], 'entrada' => $day['fecha'].' '.$shift['hora_inicio'].':00',
                        'salida' => $day['fecha'].' '.$shift['hora_fin'].':00',
                        'horas_calculadas' => $shift['duracion_horas'], 'origen' => 'manual',
                        'notas' => 'Fichaje ficticio de demostración.', 'horario_id' => $schedule->id,
                        'turno_id' => $shift['turno_id'], 'inicio_previsto' => $day['fecha'].' '.$shift['hora_inicio'].':00',
                        'fin_previsto' => $day['fecha'].' '.$shift['hora_fin'].':00',
                    ]);
                }
            }
        }
        $current = HorarioSemanal::where('estado', 'published')->where('semana_id', $today->format('o-\WW'))->sole();
        $day = collect($current->plantilla_diseno)->firstWhere('fecha', $today->toDateString());
        $shift = $day['turnos_requeridos'][0];
        $entry = CarbonImmutable::now('Europe/Madrid')->subMinutes(30)->max($today);
        Fichaje::create([
            'user_id' => $shift['empleado_id'], 'entrada' => $entry->toDateTimeString(),
            'salida' => null, 'horas_calculadas' => null, 'origen' => 'manual',
            'notas' => 'Fichaje activo ficticio para explorar la demo.', 'horario_id' => $current->id,
            'turno_id' => $shift['turno_id'], 'inicio_previsto' => $today->toDateString().' '.$shift['hora_inicio'].':00',
            'fin_previsto' => $today->toDateString().' '.$shift['hora_fin'].':00',
        ]);
    }

    private function consumption(User $admin, CarbonImmutable $today): void
    {
        app(\App\Services\DemoTemplateExamples::class)->installConsumption($admin, $today);
    }

    private function cash(Collection $users, User $admin, CarbonImmutable $today): void
    {
        $employee = $users->firstWhere('email', 'empleado@mmqp.example');
        foreach (['apertura', 'cierre'] as $shift) {
            app(SaveCuadre::class)->save([
                'fecha' => $today->toDateString(), 'turno' => $shift,
                'billetes' => ['100' => $shift === 'cierre' ? 4 : 3], 'monedas' => [],
                'billetes_ingreso' => $shift === 'cierre' ? ['100' => 1] : [], 'monedas_ingreso' => [],
                'blisters' => ['total' => '0.00'], 'nota_blanca' => '0.00', 'fondo_fijo' => '300.00',
                'dni_empleado' => preg_replace('/\D/', '', $employee->dni),
                'expected_version' => 0, 'request_id' => (string) Str::uuid(),
                'motivo' => 'Registro ficticio de demostración.',
            ], $admin);
        }
    }

    private function arqueo(Collection $users, CarbonImmutable $today): void
    {
        app(\App\Services\DemoTemplateExamples::class)->installArqueo($users, $today);
    }
}
