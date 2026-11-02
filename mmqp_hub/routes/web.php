<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\HorariosGeneradorController;
use App\Http\Controllers\PlantillaHorarioController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    if (auth()->check()) {
        if (strtolower(auth()->user()->rol) === 'oficina') {
            return redirect()->route('admin.empleados');
        }
        return redirect()->route('dashboard');
    }
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/dashboard/calendario', [DashboardController::class, 'calendario'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard.calendario');

Route::get('/dashboard/calendario-global', [DashboardController::class, 'calendarioGlobal'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard.calendario_global');

Route::middleware('auth')->group(function () {
    Route::get('/arqueo', [\App\Http\Controllers\ArqueoController::class, 'index'])->middleware('admin')->name('arqueo');
    Route::post('/arqueo/dia', [\App\Http\Controllers\ArqueoController::class, 'saveDaily'])->middleware('admin')->name('arqueo.daily.save');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // ─── PERFIL SECUNDARIOS ──────────────────────────────────────────────────
    Route::get('/perfil/datos', [ProfileController::class, 'datosPersonales'])->name('profile.datos');
    Route::get('/perfil/notificaciones', [ProfileController::class, 'notificaciones'])->name('profile.notificaciones');
    Route::get('/perfil/seguridad', [ProfileController::class, 'seguridad'])->name('profile.seguridad');

    // ─── DISPONIBILIDAD EMPLEADO ─────────────────────────────────────────────
    Route::get('/perfil/disponibilidad', [ProfileController::class, 'editDisponibilidad'])->name('profile.disponibilidad');
    Route::post('/perfil/disponibilidad', [ProfileController::class, 'updateDisponibilidad'])->name('profile.disponibilidad.update');

    // ─── VACACIONES EMPLEADO ─────────────────────────────────────────────────
    Route::get('/perfil/vacaciones', [ProfileController::class, 'vacaciones'])->name('profile.vacaciones');

    // ─── NOTIFICACIONES PUSH ────────────────────────────────────────────────
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');


    // ─── RUTAS DE ADMINISTRACIÓN ─────────────────────────────────────────────
    Route::middleware('admin')->group(function () {
        
        // ─── PANEL ADMIN ─────────────────────────────────────────────────────────
        // (Ruta /admin eliminada)
        // Empleados CRUD
        Route::get('/admin/plantillas', [AdminController::class, 'plantillas'])->name('admin.plantillas');
        Route::post('/admin/plantillas', [AdminController::class, 'storePlantilla'])->name('admin.plantillas.store');

        Route::get('/admin/arqueo', [\App\Http\Controllers\ArqueoController::class, 'index'])->name('admin.arqueo');
        Route::post('/admin/arqueo/template', [\App\Http\Controllers\ArqueoController::class, 'saveTemplate'])->name('admin.arqueo.template.save');

        Route::get('/admin/consumos', [\App\Http\Controllers\ConsumoController::class, 'index'])->name('admin.consumos');
        Route::get('/admin/consumos/historial', [\App\Http\Controllers\ConsumoController::class, 'history'])->name('admin.consumos.historial');
        Route::get('/admin/consumos/historial/{record}', [\App\Http\Controllers\ConsumoController::class, 'showRecord'])->name('admin.consumos.historial.show');
        Route::get('/admin/consumos/documento', [\App\Http\Controllers\ConsumoController::class, 'showDocument'])->name('admin.consumos.documento.show');
        Route::post('/admin/consumos/documento', [\App\Http\Controllers\ConsumoController::class, 'saveDocument'])->name('admin.consumos.documento.save');
        Route::post('/admin/consumos/fichas', [\App\Http\Controllers\ConsumoController::class, 'saveSheet'])->name('admin.consumos.fichas.save');
        Route::get('/admin/consumos/fichas/{sheet}', [\App\Http\Controllers\ConsumoController::class, 'showSheet'])->name('admin.consumos.fichas.show');
        Route::post('/admin/consumos/importar', [\App\Http\Controllers\ConsumoController::class, 'importPdf'])->name('admin.consumos.importar');
        Route::get('/admin/consumos/importaciones/{import}', [\App\Http\Controllers\ConsumoController::class, 'showImport'])->name('admin.consumos.importaciones.show');
        Route::post('/admin/consumos/importaciones/{import}/preparar', [\App\Http\Controllers\ConsumoController::class, 'prepareImport'])->name('admin.consumos.importaciones.prepare');
        Route::post('/admin/consumos/importaciones/{import}/completar', [\App\Http\Controllers\ConsumoController::class, 'completeImport'])->name('admin.consumos.importaciones.complete');

        Route::get('/admin/cuadre', [\App\Http\Controllers\CuadreCajaController::class, 'index'])->name('admin.cuadre');
        Route::post('/admin/cuadre', [\App\Http\Controllers\CuadreCajaController::class, 'store'])->name('admin.cuadre.store');
        Route::get('/admin/cuadre/historial', [\App\Http\Controllers\CuadreCajaController::class, 'historial'])->name('admin.cuadre.historial');
        Route::get('/admin/cuadre/{cuadre}/revisiones', [\App\Http\Controllers\CuadreCajaController::class, 'revisions'])->name('admin.cuadre.revisiones');

        Route::get('/admin/solicitudes', [\App\Http\Controllers\Admin\SolicitudController::class, 'index'])->name('admin.solicitudes');
        Route::put('/admin/solicitudes/{solicitud}', [\App\Http\Controllers\Admin\SolicitudController::class, 'update'])->name('admin.solicitudes.update');
        Route::get('/admin/vacaciones', [\App\Http\Controllers\Admin\SolicitudController::class, 'calendario'])->name('admin.vacaciones');
        Route::post('/admin/vacaciones', [\App\Http\Controllers\Admin\SolicitudController::class, 'storeManual'])->name('admin.vacaciones.asignar');
        Route::put('/admin/vacaciones/{id}', [\App\Http\Controllers\Admin\SolicitudController::class, 'updateManual'])->name('admin.vacaciones.actualizar');
        Route::delete('/admin/vacaciones/{id}', [\App\Http\Controllers\Admin\SolicitudController::class, 'destroyManual'])->name('admin.vacaciones.eliminar');

        Route::get('/admin/empleados', [AdminController::class, 'empleados'])->name('admin.empleados');
        Route::get('/admin/empleados/activos', [AdminController::class, 'empleadosActivos'])->name('admin.empleados.activos');
        Route::get('/admin/empleados/crear', [AdminController::class, 'crearEmpleado'])->name('admin.empleados.crear');
        Route::post('/admin/empleados/crear', [AdminController::class, 'guardarEmpleado'])->name('admin.empleados.guardar');
        Route::get('/admin/empleados/{id}/editar', [AdminController::class, 'editarEmpleado'])->name('admin.empleados.editar');
        Route::put('/admin/empleados/{id}/editar', [AdminController::class, 'actualizarEmpleado'])->name('admin.empleados.actualizar');
        Route::delete('/admin/empleados/{id}', [AdminController::class, 'eliminarEmpleado'])->name('admin.empleados.eliminar');
        Route::post('/admin/empleados/{id}/baja', [AdminController::class, 'toggleBaja'])->name('admin.empleados.baja');
        Route::post('/admin/empleados/{id}/activo', [AdminController::class, 'toggleActivo'])->name('admin.empleados.activo');
        Route::post('/admin/empleados/orden', [AdminController::class, 'guardarOrden'])->name('admin.empleados.orden');

        // ─── FICHAJES ─────────────────────────────────────────────────────────────
        Route::get('/admin/festivos', [\App\Http\Controllers\HolidayCalendarController::class, 'index'])->name('admin.festivos.index');
        Route::put('/admin/festivos', [\App\Http\Controllers\HolidayCalendarController::class, 'update'])->name('admin.festivos.update');

        Route::get('/admin/fichajes', [\App\Http\Controllers\FichajeController::class, 'index'])->name('admin.fichajes.index');
        Route::post('/admin/fichajes', [\App\Http\Controllers\FichajeController::class, 'store'])->name('admin.fichajes.store');
        Route::put('/admin/fichajes/{id}', [\App\Http\Controllers\FichajeController::class, 'update'])->name('admin.fichajes.update');
        Route::delete('/admin/fichajes/{id}', [\App\Http\Controllers\FichajeController::class, 'destroy'])->name('admin.fichajes.destroy');
        Route::post('/admin/fichajes/extra/{user_id}', [\App\Http\Controllers\FichajeController::class, 'asignarTurnoExtra'])->name('admin.fichajes.extra');

        // ─── REGISTRO DE JORNADA (INFORMES) ───────────────────────────────────────
        Route::get('/admin/registro-jornada', [\App\Http\Controllers\RegistroJornadaController::class, 'index'])->name('admin.registro-jornada.index');
        Route::get('/admin/registro-jornada/informe', [\App\Http\Controllers\RegistroJornadaController::class, 'generarInforme'])->name('admin.registro-jornada.informe');

        // ─── HORARIOS ─────────────────────────────────────────────────────────────
        Route::get('/admin/horarios', function () {
            $empleados = \App\Models\User::employees()->with(\App\Services\AbsenceAvailability::RELATIONS)->where('activo', '!=', false)
                ->get(['id', 'nombre', 'apellidos', 'rol', 'horas_mensuales', 'en_baja', 'activo', 'vacaciones', 'fechas_libres_solicitadas',
                    'tipo_disponibilidad', 'dias_disponibles', 'dias_bloqueados_recurrentes', 'disponibilidad_festivos']);
            $ausencias = \App\Models\SolicitudAusencia::whereHas('user', fn ($query) => $query->employees())->where('estado', 'aprobada')->get(['id', 'user_id', 'fecha', 'fecha_fin', 'tipo', 'motivo_empleado', 'estado']);
            $festivos = app(\App\Services\HolidayCalendar::class)->dates();
            return view('admin.horarios', compact('empleados', 'ausencias', 'festivos'));
        })->name('admin.horarios');

        Route::get('/admin/horarios/resultado/{id}', [HorariosGeneradorController::class, 'mostrarResultado'])->name('admin.horarios.resultado');
        Route::post('/admin/horarios/resultado/{id}/editar', [HorariosGeneradorController::class, 'editarHorario'])->name('admin.horarios.editar');
        Route::post('/admin/horarios/resultado/{id}/guardar', [HorariosGeneradorController::class, 'guardarHorario'])->name('admin.horarios.guardar');
        Route::put('/admin/horarios/resultado/{id}/actualizar', [HorariosGeneradorController::class, 'actualizarHorario'])->name('admin.horarios.actualizar');
        Route::post('/admin/horarios/resultado/{id}/publicar', [HorariosGeneradorController::class, 'publicarHorario'])->name('admin.horarios.publicar');
        
        Route::get('/admin/horarios/historial', [HorariosGeneradorController::class, 'historial'])->name('admin.horarios.historial');
        Route::get('/admin/horarios/exportar/{id?}', [\App\Http\Controllers\ScheduleTransferController::class, 'export'])->whereNumber('id')->name('admin.horarios.exportar');
        Route::post('/admin/horarios/importar', [\App\Http\Controllers\ScheduleTransferController::class, 'import'])->name('admin.horarios.importar');
        Route::delete('/admin/horarios/historial/{id}', [HorariosGeneradorController::class, 'eliminarHistorial'])->name('admin.horarios.historial.eliminar');

        // ─── API PLANTILLAS ───────────────────────────────────────────────────────
        Route::get('/api/plantillas', [PlantillaHorarioController::class, 'index']);
        Route::post('/api/plantillas', [PlantillaHorarioController::class, 'store']);
        Route::delete('/api/plantillas/{id}', [PlantillaHorarioController::class, 'destroy']);
        
        // API HORARIOS PASADOS 
        Route::get('/api/horarios-semanales', function() {
            // Retornamos todos los horarios pasados ordenados por fecha de inicio
            return response()->json(\App\Models\HorarioSemanal::orderBy('fecha_inicio', 'desc')->get());
        });
        // API AUTO-GUARDADO (Borradores)
        Route::post('/api/horarios-semanales/borrador/abrir', [HorariosGeneradorController::class, 'abrirBorrador']);
        Route::post('/api/horarios-semanales/borrador', [HorariosGeneradorController::class, 'guardarBorrador']);

        // 🤖 API GENERADOR IA 🤖─────────────────────────────────────────────────────
        Route::post('/api/generar-horario', [HorariosGeneradorController::class, 'generar']);
        Route::match(['get', 'post'], '/api/generar-plantilla-ia', [HorariosGeneradorController::class, 'generarPlantillaIA']);
        Route::post('/api/eventos-locales', [HorariosGeneradorController::class, 'getEventosLocales']);
    });
});

// ─── API KIOSKO (Tablet) ──────────────────────────────────────────────────
Route::prefix('api/kiosko')->group(function () {
    Route::get('/status', [\App\Http\Controllers\KioskoController::class, 'status']);
    Route::post('/fichar', [\App\Http\Controllers\KioskoController::class, 'fichar']);
});

require __DIR__.'/auth.php';
