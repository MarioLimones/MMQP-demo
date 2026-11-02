<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{




    // ─── CRUD EMPLEADOS ────────────────────────────────────────────────────────

    public function empleados(Request $request)
    {
        $filters = array_replace([
            'buscar' => '', 'estado' => 'activos', 'rol' => '',
            'situacion' => '', 'disponibilidad' => '', 'vista' => 'grid',
        ], $request->validate([
            'buscar' => 'nullable|string|max:200',
            'estado' => 'nullable|in:activos,inactivos,todos',
            'rol' => 'nullable|in:repartidor,encargado,auxiliar,limpiadora,admin,oficina',
            'situacion' => 'nullable|in:alta,baja',
            'disponibilidad' => 'nullable|in:completa,personalizada',
            'vista' => 'nullable|in:grid,list',
        ]));
        $filters['buscar'] = \Illuminate\Support\Str::squish($filters['buscar'] ?? '');
        $filters['estado'] = $filters['estado'] ?: 'todos';
        $filters['vista'] = $filters['vista'] ?: 'grid';
        $query = User::employees()->with(\App\Services\AbsenceAvailability::RELATIONS);

        $estado = $filters['estado'];
        if ($estado === 'activos') {
            $query->where('activo', true);
        } elseif ($estado === 'inactivos') {
            $query->where('activo', false);
        }

        // Every term may match a different identity field (e.g. name + surname).
        // Escape SQL wildcards so literal DNI, email and phone fragments stay precise.
        foreach (preg_split('/\s+/u', $filters['buscar'], -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
            $query->where(function ($match) use ($pattern, $term) {
                foreach (['nombre', 'apellidos', 'email', 'dni', 'telefono'] as $column) {
                    $match->orWhereRaw($column." LIKE ? ESCAPE '!'", [$pattern]);
                }
                if (preg_match('/^[+0-9().-]+$/D', $term) && ($digits = preg_replace('/\D/', '', $term)) !== '') {
                    $match->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE ?", ['%'.$digits.'%']);
                }
            });
        }
        if ($filters['rol']) {
            $query->where('rol', $filters['rol']);
        }
        if ($filters['situacion']) {
            $query->where('en_baja', $filters['situacion'] === 'baja');
        }
        if ($filters['disponibilidad']) {
            $query->where('tipo_disponibilidad', $filters['disponibilidad']);
        }
        $empleados = $query->orderBy('fecha_inicio', 'asc')->orderBy('id', 'asc')->paginate(20)->withQueryString();

        $hasFilters = $filters['buscar'] !== '' || $estado !== 'todos' || $filters['rol']
            || $filters['situacion'] || $filters['disponibilidad'];

        return view('admin.empleados.index', compact('empleados', 'filters', 'hasFilters'));
    }

    public function empleadosActivos()
    {
        $empleados = User::employees()->where('activo', true)
                         ->where('en_baja', false)
                         ->orderBy('rol')
                         ->orderBy('nombre')
                         ->get();
                         
        $agrupados = $empleados->groupBy('rol');
        
        return view('admin.empleados.activos', compact('agrupados'));
    }

    public function crearEmpleado()
    {
        return view('admin.empleados.form', ['empleado' => null]);
    }

    public function guardarEmpleado(Request $request)
    {
        $data = $request->validate([
            'nombre'                      => 'required|string|max:100',
            'apellidos'                   => 'nullable|string|max:100',
            'dni'                         => 'nullable|string|max:20|unique:users,dni',
            'telefono'                    => 'nullable|string|max:20',
            'email'                       => 'nullable|email|unique:users,email',
            'password'                    => 'nullable|string|min:6',
            'rol'                         => ['required', Rule::in(User::EMPLOYEE_ROLES)],
            'horas_mensuales'             => 'nullable|numeric|min:0',
            'tipo_disponibilidad'         => 'required|in:completa,personalizada',
            'disponibilidad_festivos'      => 'sometimes|required|in:semanal,disponible,no_disponible',
            'dias_disponibles'            => 'nullable|array:lunes,martes,miercoles,jueves,viernes,sabado,domingo',
            'dias_disponibles.*'          => 'array',
            'dias_disponibles.*.*'        => 'string|in:mañana,tarde',
            'es_admin'                    => 'nullable|boolean',
            'es_menor'                    => 'nullable|boolean',
            'fecha_inicio'                => 'nullable|date',
            'fecha_fin_contrato'          => ['nullable', 'date', Rule::when($request->filled('fecha_inicio'), 'after_or_equal:fecha_inicio')],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $data['dias_bloqueados_recurrentes'] = []; // Deprecated concept in UI
        $data['dias_disponibles']            = $request->input('dias_disponibles', []);
        $data['es_admin']                    = $request->boolean('es_admin');
        $data['es_menor']                    = $request->boolean('es_menor');
        $data['activo'] = true;

        User::create($data);
        return redirect()->route('admin.empleados')->with('success', 'Empleado creado correctamente.');
    }

    public function editarEmpleado($id)
    {
        $empleado = User::employees()->findOrFail($id);
        return view('admin.empleados.form', compact('empleado'));
    }

    public function actualizarEmpleado(Request $request, $id)
    {
        $empleado = User::employees()->findOrFail($id);

        $data = $request->validate([
            'nombre'                      => 'required|string|max:100',
            'apellidos'                   => 'nullable|string|max:100',
            'dni'                         => ['nullable', 'string', 'max:20', Rule::unique('users', 'dni')->ignore($id)],
            'telefono'                    => 'nullable|string|max:20',
            'email'                       => ['nullable', 'email', Rule::unique('users', 'email')->ignore($id)],
            'password'                    => 'nullable|string|min:6',
            'rol'                         => ['required', Rule::in(User::EMPLOYEE_ROLES)],
            'horas_mensuales'             => 'nullable|numeric|min:0',
            'tipo_disponibilidad'         => 'required|in:completa,personalizada',
            'disponibilidad_festivos'      => 'sometimes|required|in:semanal,disponible,no_disponible',
            'dias_disponibles'            => 'nullable|array:lunes,martes,miercoles,jueves,viernes,sabado,domingo',
            'dias_disponibles.*'          => 'array',
            'dias_disponibles.*.*'        => 'string|in:mañana,tarde',
            'en_baja'                     => 'nullable|boolean',
            'baja_desde'                  => 'nullable|date',
            'baja_motivo'                 => 'nullable|string|max:500',
            'es_admin'                    => 'nullable|boolean',
            'es_menor'                    => 'nullable|boolean',
            'fecha_inicio'                => 'nullable|date',
            'fecha_fin_contrato'          => ['nullable', 'date', Rule::when($request->filled('fecha_inicio'), 'after_or_equal:fecha_inicio')],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $data['dias_bloqueados_recurrentes'] = []; // Deprecated concept in UI
        $data['dias_disponibles']            = $request->input('dias_disponibles', []);
        $data['en_baja'] = $request->boolean('en_baja');
        $data['es_admin'] = $request->boolean('es_admin');
        $data['es_menor'] = $request->boolean('es_menor');

        $empleado->update($data);
        return redirect()->route('admin.empleados')->with('success', 'Empleado actualizado correctamente.');
    }

    public function eliminarEmpleado($id)
    {
        if (auth()->id() == $id) {
            return back()->with('error', 'No puedes eliminarte a ti mismo.');
        }
        User::employees()->findOrFail($id)->delete();
        return back()->with('success', 'Empleado eliminado.');
    }

    public function toggleBaja(Request $request, $id)
    {
        $empleado = User::employees()->findOrFail($id);
        $empleado->update([
            'en_baja'     => !$empleado->en_baja,
            'baja_desde'  => !$empleado->en_baja ? now()->toDateString() : null,
            'baja_motivo' => !$empleado->en_baja ? ($request->motivo ?? 'Baja médica') : null,
        ]);
        $msg = $empleado->en_baja ? "{$empleado->nombre} puesto en baja." : "{$empleado->nombre} dado de alta.";
        return back()->with('success', $msg);
    }

    public function toggleActivo($id)
    {
        $empleado = User::employees()->findOrFail($id);
        $empleado->update([
            'activo' => !$empleado->activo
        ]);
        $msg = $empleado->activo ? "El contrato de {$empleado->nombre} ha sido reactivado." : "El contrato de {$empleado->nombre} ha finalizado.";
        return back()->with('success', $msg);
    }

    public function guardarOrden(Request $request)
    {
        $orden = $request->input('orden'); // Array of objects
        if (is_array($orden)) {
            foreach ($orden as $item) {
                if (isset($item['id']) && isset($item['orden_visual'])) {
                    \App\Models\User::employees()->where('id', $item['id'])->update(['orden_visual' => $item['orden_visual']]);
                }
            }
        }
        return response()->json(['success' => true]);
    }
}
