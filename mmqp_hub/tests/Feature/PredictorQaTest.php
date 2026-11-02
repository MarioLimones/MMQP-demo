<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PredictorQaTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_examples_work_without_credentials_or_network(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create(['es_admin' => true, 'rol' => 'oficina']));
        $this->postJson('/api/eventos-locales', [
            'fecha_inicio' => '2026-11-02', 'fecha_fin' => '2026-11-08',
        ])->assertOk()->assertJsonPath('demo', true);
        Http::assertNothingSent();
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create(['es_admin' => true, 'rol' => 'oficina']));
        $this->postJson('/api/eventos-locales', [
            'fecha_inicio' => '2026-11-02', 'fecha_fin' => '2026-11-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
        Http::assertNothingSent();
    }

    public function test_employee_role_cannot_invoke_event_prediction(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create(['es_admin' => false, 'rol' => 'auxiliar']));
        $this->postJson('/api/eventos-locales', [
            'fecha_inicio' => '2026-11-02', 'fecha_fin' => '2026-11-08',
        ])->assertForbidden();
        Http::assertNothingSent();
    }
}
