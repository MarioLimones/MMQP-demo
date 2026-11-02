<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DemoHelp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class DemoHelpTest extends TestCase
{
    use RefreshDatabase;

    private function help(string $name, string $query = '', ?User $user = null): ?array
    {
        $request = Request::create('/example'.$query);
        $request->setRouteResolver(fn () => (new Route('GET', '/example', fn () => ''))->name($name));
        $request->setUserResolver(fn () => $user);

        return app(DemoHelp::class)->forRequest($request);
    }

    public function test_personal_guidance_matches_the_account_instead_of_its_admin_permissions(): void
    {
        $admin = User::factory()->make(['rol' => 'admin', 'es_admin' => true]);
        $employee = User::factory()->make(['rol' => 'encargado', 'es_admin' => false]);
        $office = User::factory()->make(['rol' => 'oficina', 'es_admin' => true]);
        foreach (['dashboard', 'dashboard.calendario', 'profile.edit', 'profile.datos', 'profile.notificaciones'] as $route) {
            $adminHelp = $this->help($route, '', $admin);
            $employeeHelp = $this->help($route, '', $employee);
            $this->assertNotSame($adminHelp['title'], $employeeHelp['title']);
            $this->assertStringContainsString('jornada', implode(' ', $adminHelp['paragraphs']));
            $this->assertSame($employeeHelp['title'], $this->help($route, '', $office)['title']);
        }
        $this->assertSame('Abre el calendario para ver cómo continúa la semana.', $this->help('dashboard', '', $employee)['tip']);
        $this->assertStringContainsString('Calendario Global', $this->help('dashboard', '', $admin)['tip']);
    }

    public function test_calendar_renders_account_specific_help_for_admin_and_worker(): void
    {
        $admin = User::factory()->create(['rol' => 'admin', 'es_admin' => true]);
        $employee = User::factory()->create(['rol' => 'encargado', 'es_admin' => false]);
        $this->actingAs($admin)->get('/dashboard/calendario')->assertOk()
            ->assertSee('es normal que su calendario personal esté vacío');
        $this->actingAs($employee)->get('/dashboard/calendario')->assertOk()
            ->assertSee('Tus turnos, de un vistazo')
            ->assertDontSee('es normal que su calendario personal esté vacío');
    }

    public function test_each_section_has_help_and_editor_modes_explain_different_tasks(): void
    {
        foreach (['dashboard', 'dashboard.calendario', 'dashboard.calendario_global',
            'admin.empleados', 'admin.empleados.activos', 'admin.empleados.crear', 'admin.empleados.editar',
            'admin.horarios', 'admin.horarios.resultado', 'admin.horarios.historial', 'admin.plantillas',
            'admin.arqueo', 'arqueo', 'admin.consumos', 'admin.consumos.documento.show',
            'admin.consumos.historial', 'admin.consumos.historial.show', 'admin.consumos.fichas.show',
            'admin.cuadre', 'admin.cuadre.historial', 'admin.solicitudes', 'admin.vacaciones',
            'admin.festivos.index', 'admin.fichajes.index', 'admin.registro-jornada.index',
            'profile.edit', 'profile.datos', 'profile.disponibilidad', 'profile.vacaciones',
            'profile.notificaciones', 'profile.seguridad'] as $name) {
            $this->assertNotNull($this->help($name), 'Falta la ayuda de '.$name);
        }

        $entry = $this->help('admin.arqueo');
        $design = $this->help('admin.arqueo', '?modo=editar');
        $daily = $this->help('admin.arqueo', '?modo=rellenar');
        $this->assertNotSame($entry['title'], $design['title']);
        $this->assertNotSame($design['title'], $daily['title']);
    }

    public function test_help_is_optional_native_disclosure_and_exclusive_to_demo(): void
    {
        $admin = User::factory()->create(['rol' => 'admin', 'es_admin' => true]);
        $response = $this->actingAs($admin)->get('/admin/empleados')->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $details = $xpath->query('//details[@data-demo-help]');
        $this->assertSame(1, $details->length);
        $this->assertFalse($details->item(0)->hasAttribute('open'));
        $this->assertSame(1, $xpath->query('./summary', $details->item(0))->length);

        config(['demo.enabled' => false]);
        $this->get('/admin/empleados')->assertOk()->assertDontSee('data-demo-help=', false);
        $this->assertNull($this->help('admin.empleados'));
    }
}
