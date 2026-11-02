<?php
namespace Tests\Feature;

use App\Models\HorarioSemanal;
use App\Models\User;
use App\Notifications\UpcomingShiftNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpcomingShiftReminderTest extends TestCase
{
    use RefreshDatabase;

    public static function accentedDays(): array
    {
        return [['2026-10-20 12:00:00', 'Miércoles'], ['2026-10-23 12:00:00', 'Sábado']];
    }

    #[DataProvider('accentedDays')]
    public function test_accented_weekday_reminder_uses_actual_tomorrow(string $now, string $day): void
    {
        Carbon::setTestNow($now);
        try {
            Notification::fake();
            $user = User::factory()->create(['activo' => true, 'en_baja' => false, 'rol' => 'repartidor']);
            $user->updatePushSubscription('https://push.example.test/only-fictitious-qa', 'qa-public', 'qa-auth');
            $tomorrow = Carbon::tomorrow();
            HorarioSemanal::create([
                'semana_id' => $tomorrow->format('o-\\WW'),
                'fecha_inicio' => $tomorrow->copy()->startOfWeek()->format('Y-m-d'),
                'fecha_fin' => $tomorrow->copy()->endOfWeek()->format('Y-m-d'),
                'plantilla_diseno' => [['dia' => $day, 'turnos_requeridos' => [
                    ['turno_id' => 'qa-target', 'hora_inicio' => '09:00', 'hora_fin' => '12:00'],
                ]]],
                'resultados' => [['empleado_id' => $user->id, 'turno_id' => 'qa-target']],
                'empleados_snapshot' => [['id' => $user->id, 'nombre' => $user->nombre]],
                'generado_por' => $user->id, 'turnos_cubiertos' => 1, 'turnos_totales' => 1,
                'estado' => 'publicado', 'vigente' => true,
                'roles_publicados' => ['repartidor'],
            ]);
            $this->artisan('turnos:recordar')->assertExitCode(0);
            Notification::assertSentTo($user, UpcomingShiftNotification::class, fn ($notification) =>
                $notification->shiftDate === $tomorrow->format('d/m/Y') && $notification->shiftTime === '09:00');
        } finally {
            Carbon::setTestNow();
        }
    }

    public static function excludedRecipients(): array
    {
        return [
            'inactive' => [false, false, ['repartidor'], 'lunes'],
            'on-leave' => [true, true, ['repartidor'], 'lunes'],
            'role-not-published' => [true, false, ['auxiliar'], 'lunes'],
            'unknown-day' => [true, false, ['repartidor'], 'desconocido'],
        ];
    }

    #[DataProvider('excludedRecipients')]
    public function test_reminders_exclude_inactive_unpublished_or_unknown_day(bool $active, bool $onLeave, array $roles, string $day): void
    {
        Carbon::setTestNow('2026-10-18 12:00:00');
        try {
            Notification::fake();
            $user = User::factory()->create(['activo' => $active, 'en_baja' => $onLeave, 'rol' => 'repartidor']);
            $user->updatePushSubscription('https://push.example.test/excluded-qa', 'qa-public', 'qa-auth');
            HorarioSemanal::create([
                'semana_id' => '2026-W43', 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-25',
                'plantilla_diseno' => [['dia' => $day, 'turnos_requeridos' => [
                    ['turno_id' => 'qa-excluded', 'hora_inicio' => '09:00', 'hora_fin' => '12:00'],
                ]]],
                'resultados' => [['empleado_id' => $user->id, 'turno_id' => 'qa-excluded']],
                'empleados_snapshot' => [['id' => $user->id, 'nombre' => $user->nombre]],
                'generado_por' => $user->id, 'turnos_cubiertos' => 1, 'turnos_totales' => 1,
                'estado' => 'published', 'vigente' => true, 'roles_publicados' => $roles,
            ]);
            $this->artisan('turnos:recordar')->assertExitCode(0);
            Notification::assertNothingSent();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_reminder_payload_has_date_time_and_dashboard_link(): void
    {
        $notification = new UpcomingShiftNotification('24/10/2026', '09:00 y a las 17:00');
        $payload = $notification->toWebPush(User::factory()->make(), $notification)->toArray();
        $this->assertSame('Próximo turno', $payload['title']);
        $this->assertStringContainsString('24/10/2026', $payload['body']);
        $this->assertStringContainsString('09:00 y a las 17:00', $payload['body']);
        $this->assertSame(url('/dashboard'), $payload['data']['url']);
        $this->assertSame('ver_horario', $payload['actions'][0]['action']);
    }
}
