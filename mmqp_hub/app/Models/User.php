<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    public const EMPLOYEE_ROLES = ['repartidor', 'encargado', 'auxiliar', 'limpiadora', 'oficina'];

    public function isEmployee(): bool
    {
        return $this->rol !== 'admin';
    }

    /** Technical administrator accounts are not part of the workforce. */
    public function scopeEmployees(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('rol'), '!=', 'admin');
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasPushSubscriptions;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'apellidos',
        'dni',
        'telefono',
        'email',
        'password',
        'rol',
        'orden_visual',
        'horas_mensuales',
        'tipo_disponibilidad',
        'disponibilidad_festivos',
        'dias_bloqueados_recurrentes',
        'dias_disponibles',
        'en_baja',
        'baja_desde',
        'baja_motivo',
        'es_admin',
        'es_menor',
        'activo',
        'fecha_inicio',
        'fecha_fin_contrato',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'dias_bloqueados_recurrentes' => 'array',
            'dias_disponibles'           => 'array',
            'fechas_libres_solicitadas' => 'array',
            'vacaciones' => 'array',
            'horas_mensuales' => 'float',
            'en_baja' => 'boolean',
            'baja_desde' => 'date',
            'es_admin' => 'boolean',
            'es_menor' => 'boolean',
            'activo' => 'boolean',
            'fecha_inicio' => 'date',
            'fecha_fin_contrato' => 'date',
        ];
    }

    public function solicitudesAusencia()
    {
        return $this->hasMany(SolicitudAusencia::class);
    }

    public function fichajes()
    {
        return $this->hasMany(Fichaje::class);
    }

    public function ausenciasVigentes()
    {
        return $this->hasMany(SolicitudAusencia::class)->where('estado', 'aprobada');
    }

    public function availabilityBlocks()
    {
        return $this->hasMany(AvailabilityBlock::class);
    }

    public function legacyAvailabilityImport()
    {
        return $this->hasOne(LegacyAvailabilityImport::class);
    }

    public function getVacacionesAttribute(): array
    {
        return app(\App\Services\AbsenceAvailability::class)->vacationDates($this);
    }

    public function getFechasLibresSolicitadasAttribute(): array
    {
        return app(\App\Services\AbsenceAvailability::class)->blockedDates($this);
    }
}
