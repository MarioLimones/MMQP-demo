<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkerArqueoAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $originalStorage;
    private string $temporaryStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalStorage = storage_path();
        $this->temporaryStorage = sys_get_temp_dir().'/mmqp-worker-access-'.Str::uuid();
        File::ensureDirectoryExists($this->temporaryStorage.'/app');
        $this->app->useStoragePath($this->temporaryStorage);
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->originalStorage);
        File::deleteDirectory($this->temporaryStorage);
        parent::tearDown();
    }

    public function test_workers_keep_personal_pages_without_an_arqueo_navigation_link(): void
    {
        $employee = User::factory()->create(['rol' => 'encargado', 'es_admin' => false]);
        foreach (['/dashboard', '/dashboard/calendario', '/profile'] as $url) {
            $response = $this->actingAs($employee)->get($url)->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame(0, $xpath->query('//nav//a[contains(@href, "/arqueo")]')->length);
            $this->assertGreaterThan(0, $xpath->query('//nav//a[contains(@href, "/dashboard/calendario")]')->length);
        }
    }

    public function test_workers_cannot_read_or_write_arqueo_through_either_route(): void
    {
        $employee = User::factory()->create(['rol' => 'encargado', 'es_admin' => false]);
        $path = storage_path('app/arqueo_template.json');
        File::put($path, '{"html":"<tr><td>Example untouched</td></tr>"}');
        $before = File::get($path);
        $this->actingAs($employee);
        foreach (['/arqueo', '/arqueo?modo=rellenar', '/arqueo?modo=editar', '/admin/arqueo'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->postJson('/arqueo/dia', ['fecha' => '2026-11-02', 'values' => ['0-0' => 'Should not save']])->assertForbidden();
        $this->postJson('/admin/arqueo/template', ['template' => ['html' => '<tr><td>Should not save</td></tr>']])->assertForbidden();
        $this->assertSame($before, File::get($path));
        $this->assertFalse(File::isDirectory(storage_path('app/arqueos')));
    }

    public function test_administrators_and_office_keep_arqueo_access(): void
    {
        foreach (['admin', 'oficina'] as $role) {
            $admin = User::factory()->create(['rol' => $role, 'es_admin' => true]);
            $this->actingAs($admin)->get('/admin/arqueo')->assertOk()->assertSee('Arqueo');
            $this->get('/arqueo?modo=rellenar')->assertOk();
            $this->get('/admin/arqueo?modo=editar')->assertOk();
        }
    }
}
