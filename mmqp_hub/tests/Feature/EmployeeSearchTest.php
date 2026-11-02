<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['es_admin' => true, 'activo' => true]);
        $this->employee = User::factory()->create([
            'nombre' => 'Ana María', 'apellidos' => 'López García', 'dni' => '12345678Z',
            'email' => 'ana_maria@example.test', 'telefono' => '612 345 678',
            'rol' => 'auxiliar', 'activo' => true, 'en_baja' => false,
            'tipo_disponibilidad' => 'personalizada',
        ]);
        $this->actingAs($this->admin);
    }

    private function search(array $filters)
    {
        return $this->get(route('admin.empleados', $filters));
    }

    public function test_identity_search_combines_words_across_fields_and_trims_whitespace(): void
    {
        $this->search(['buscar' => '  García   Ana  '])->assertOk()
            ->assertViewHas('empleados', fn ($users) => $users->modelKeys() === [$this->employee->id]);
        foreach (['12345678', '612 345', '612345678', '612-345-678', 'ana_maria@example.test'] as $term) {
            $this->search(['buscar' => $term])->assertOk()
                ->assertViewHas('empleados', fn ($users) => $users->modelKeys() === [$this->employee->id]);
        }
    }

    public function test_all_filters_are_applied_together_and_inactive_employees_are_queryable(): void
    {
        User::factory()->create(['nombre' => 'Ana distinta', 'rol' => 'auxiliar', 'activo' => true, 'en_baja' => true]);
        $filters = ['buscar' => 'Ana', 'rol' => 'auxiliar', 'estado' => 'activos', 'situacion' => 'alta', 'disponibilidad' => 'personalizada'];
        $this->search($filters)->assertOk()
            ->assertViewHas('empleados', fn ($users) => $users->modelKeys() === [$this->employee->id]);
        $this->employee->update(['activo' => false]);
        $this->search($filters)->assertViewHas('empleados', fn ($users) => $users->isEmpty());
        $this->search(array_replace($filters, ['estado' => 'inactivos']))
            ->assertViewHas('empleados', fn ($users) => $users->modelKeys() === [$this->employee->id]);
    }

    public function test_reset_clears_every_filter_and_empty_results_offer_recovery(): void
    {
        $this->employee->update(['activo' => false]);
        $response = $this->search(['buscar' => 'no-coincide', 'estado' => 'inactivos', 'rol' => 'auxiliar', 'situacion' => 'baja']);
        $response->assertOk()->assertSee('Ningún empleado encontrado')->assertSee('Ver todos los empleados')
            ->assertSee(route('admin.empleados', ['estado' => 'todos', 'vista' => 'grid']));
        $this->search(['estado' => 'todos'])->assertOk()
            ->assertViewHas('empleados', fn ($users) => $users->total() === 2)
            ->assertViewHas('hasFilters', false);
    }

    public function test_search_treats_sql_wildcards_literally_and_rejects_invalid_filters(): void
    {
        $this->search(['buscar' => '%'])->assertOk()->assertViewHas('empleados', fn ($users) => $users->isEmpty());
        User::factory()->create(['nombre' => 'Ana alternativa', 'email' => 'anaXmaria@example.test', 'activo' => true]);
        $this->search(['buscar' => 'ana_maria'])->assertViewHas('empleados', fn ($users) => $users->modelKeys() === [$this->employee->id]);
        $this->getJson(route('admin.empleados', ['estado' => 'incorrecto', 'buscar' => ['bad']]))
            ->assertUnprocessable()->assertJsonValidationErrors(['estado', 'buscar']);
    }

    public function test_pagination_preserves_combined_filters_and_presentation(): void
    {
        User::factory()->count(22)->create(['nombre' => 'Coincidencia', 'rol' => 'auxiliar', 'activo' => true, 'en_baja' => false]);
        $filters = ['buscar' => 'Coincidencia', 'rol' => 'auxiliar', 'situacion' => 'alta', 'estado' => 'activos', 'vista' => 'list'];
        $this->search($filters)->assertOk()->assertViewHas('empleados', function ($users) {
            $url = $users->nextPageUrl();
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            return $users->count() === 20 && $users->total() === 22
                && $query['buscar'] === 'Coincidencia' && $query['rol'] === 'auxiliar'
                && $query['situacion'] === 'alta' && $query['vista'] === 'list' && $query['page'] === '2';
        });
        $this->search($filters + ['page' => 2])->assertViewHas('empleados', fn ($users) => $users->count() === 2);
    }

    public function test_employee_without_admin_privileges_cannot_access_master(): void
    {
        $this->actingAs($this->employee)->get(route('admin.empleados'))->assertForbidden();
    }
}
