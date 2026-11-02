<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_is_identified_and_push_is_disabled(): void
    {
        $this->get('/login')->assertOk()->assertSee('Datos ficticios')->assertSee('admin@mmqp.example')
            ->assertHeader('X-MMQP-Demo', 'synthetic-data');
        $this->postJson('/push/subscribe', [])->assertForbidden();
        $this->assertSame('array', config('mail.default'));
    }

    public function test_demo_events_do_not_contact_an_external_service(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $admin = User::factory()->create(['rol' => 'admin', 'es_admin' => true]);
        $this->actingAs($admin)->postJson('/api/eventos-locales', [
            'fecha_inicio' => '2026-11-02', 'fecha_fin' => '2026-11-08',
        ])->assertOk()->assertJsonPath('demo', true);
        Http::assertNothingSent();
    }
}
