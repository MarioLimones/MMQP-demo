<?php

namespace App\Http\Requests;

use App\Models\CuadreRevision;
use App\Models\User;
use App\Services\CuadreCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCuadreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->es_admin;
    }

    public function rules(): array
    {
        $money = function ($attribute, $value, $fail) {
            if ((! is_string($value) && ! is_int($value)) || ! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', (string) $value)) {
                $fail('Introduce un importe entre 0 y 99.999.999,99 €, con punto decimal y como máximo dos decimales.');
            }
        };
        $counts = function (array $denominations) {
            return function ($attribute, $value, $fail) use ($denominations) {
                if (! is_array($value)) {
                    $fail('Introduce el desglose de unidades.');

                    return;
                }
                if (array_diff(array_keys($value), array_keys($denominations))) {
                    $fail('El desglose contiene una denominación no admitida.');
                }
                foreach ($value as $count) {
                    if ((! is_int($count) && ! is_string($count)) || ! preg_match('/^\d{1,6}$/D', (string) $count) || (int) $count > CuadreCalculator::MAX_COUNT) {
                        $fail('Cada cantidad debe ser un entero entre 0 y 999.999. Los campos vacíos no equivalen a cero.');
                        break;
                    }
                }
                // Missing denominations mean zero; explicit empty/invalid values are never converted to zero.
            };
        };
        $rules = [
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31'],
            'turno' => ['required', Rule::in(['apertura', 'intermedio', 'cierre'])],
            'billetes' => ['present', 'array', $counts(CuadreCalculator::BILLETES)],
            'monedas' => ['present', 'array', $counts(CuadreCalculator::MONEDAS)],
            'billetes_ingreso' => ['sometimes', 'array', $counts(CuadreCalculator::BILLETES)],
            'monedas_ingreso' => ['sometimes', 'array', $counts(CuadreCalculator::MONEDAS)],
            'blisters' => ['required', 'array:total'],
            'blisters.total' => ['required', $money],
            'blisters_ingreso' => ['prohibited'],
            'nota_blanca' => ['required', $money],
            'fondo_fijo' => ['required', $money],
            'venta_parcial' => ['required_if:turno,intermedio', 'filled', $money],
            'expected_version' => ['required', 'integer', 'min:0', 'max:2147483646'],
            'request_id' => ['required', 'uuid'],
            'motivo' => ['nullable', 'string', 'max:1000'],
            'dni_empleado' => ['required', 'string', 'regex:/^\d{8}$/D'],
        ];
        foreach (['gastos', 'cambios', 'repartos', 'pedidos_hora_exacta', 'otros'] as $field) {
            $rules[$field] = ['required_if:turno,intermedio', 'filled', $money];
        }

        // total_calculado, ingreso_real, descuadre and client-supplied audit metadata are intentionally ignored.
        return $rules;
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach (['billetes', 'monedas'] as $field) {
                foreach ($this->input($field.'_ingreso', []) as $key => $count) {
                    if ($this->turno !== 'cierre' && (int) $count !== 0) {
                        $validator->errors()->add($field.'_ingreso', 'Solo el turno de cierre admite unidades destinadas al ingreso.');
                    } elseif ((int) $count > (int) ($this->input($field, [])[$key] ?? 0)) {
                        $validator->errors()->add($field.'_ingreso', 'Las unidades al ingreso no pueden superar las contadas en caja.');
                    }
                }
            }
            if ($this->turno !== 'intermedio') {
                foreach (['gastos', 'cambios', 'repartos', 'pedidos_hora_exacta', 'otros', 'venta_parcial'] as $field) {
                    if (CuadreCalculator::cents($this->input($field, '0')) !== 0) {
                        $validator->errors()->add($field, 'Esta partida solo se utiliza en el turno intermedio.');
                    }
                }
            }
            // Completed retries use the frozen responsible reference even if that employee was removed later.
            // SaveCuadre still checks the canonical payload and authenticated actor before replaying.
            if (CuadreRevision::where('request_id', $this->input('request_id'))->exists()) {
                return;
            }
            $dni = $this->input('dni_empleado');
            $employees = User::employees()->where(fn ($query) => $query->where('dni', $dni)->orWhereRaw('UPPER(dni) LIKE ?', [$dni.'_']))->get()
                ->filter(fn ($user) => preg_match('/^'.preg_quote($dni, '/').'[A-Za-z]?$/D', $user->dni));
            if ($employees->count() !== 1) {
                $validator->errors()->add('dni_empleado', 'Introduce los ocho dígitos de un empleado existente e inequívoco.');
            }
        }];
    }

    public function messages(): array
    {
        return ['required' => 'Este campo es obligatorio; introduce 0 si no hay importe.',
            'required_if' => 'Este campo es obligatorio en el turno intermedio; introduce 0 si no hay importe.',
            'dni_empleado.regex' => 'El DNI debe contener exactamente ocho dígitos, sin letra.',
            'dni_empleado.string' => 'Envía el DNI como texto de ocho dígitos, conservando los ceros iniciales.',
            'fecha.date_format' => 'Introduce una fecha real con formato AAAA-MM-DD.',
            'turno.in' => 'Selecciona apertura, intermedio o cierre.',
            'expected_version.required' => 'Recarga el cierre antes de guardar: falta la versión que estás editando.',
            'request_id.required' => 'Recarga el formulario antes de guardar: falta el identificador del envío.'];
    }
}
