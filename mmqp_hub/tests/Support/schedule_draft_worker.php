<?php

use App\Models\HorarioSemanal;
use App\Models\User;
use App\Services\ScheduleDrafts;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';
$database = realpath($argv[1]);
$directory = $database ? dirname($database) : '';
$temporary = realpath(sys_get_temp_dir());
if (! $database || ! $temporary || ! str_starts_with($directory, $temporary.DIRECTORY_SEPARATOR)
    || ! str_starts_with(basename($directory), 'mmqp-draft-') || basename($database) !== 'test.sqlite') {
    throw new RuntimeException('Draft concurrency fixture requires its own temporary SQLite file.');
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database,
    'database.connections.sqlite.url' => null, 'cache.default' => 'array']);
DB::purge('sqlite');
$mode = $argv[2];
if ($mode === 'init') {
    Artisan::call('migrate', ['--force' => true]);
    $admin = User::factory()->create(['id' => 1, 'es_admin' => true, 'rol' => 'oficina', 'nombre' => 'Draft QA', 'email' => 'draft-qa@example.test']);
    auth()->login($admin);
    $publication = HorarioSemanal::create(['semana_id' => '2030-W01', 'fecha_inicio' => '2029-12-31', 'fecha_fin' => '2030-01-06',
        'plantilla_diseno' => [], 'resultados' => [], 'empleados_snapshot' => [], 'roles_publicados' => ['encargado'],
        'estado' => 'published', 'vigente' => true, 'version' => 1, 'generado_por' => $admin->id]);
    $draft = app(ScheduleDrafts::class)->open('2030-W01');
    echo json_encode(['draft_id' => $draft->id, 'publication_hash' => hash('sha256', json_encode($publication->fresh()->getRawOriginal()))]);
} elseif ($mode === 'inspect') {
    $draft = HorarioSemanal::where('draft_slot', 1)->sole();
    $publication = HorarioSemanal::where('estado', 'published')->sole();
    echo json_encode(['drafts' => HorarioSemanal::where('draft_slot', 1)->count(), 'version' => $draft->version,
        'note' => $draft->plantilla_diseno[0]['notas'] ?? null, 'state' => $draft->estado, 'current' => $draft->vigente,
        'publication_hash' => hash('sha256', json_encode($publication->getRawOriginal()))]);
} else {
    auth()->login(User::findOrFail(1));
    $payload = json_decode(file_get_contents($argv[3]), true, 512, JSON_THROW_ON_ERROR);
    if (! empty($argv[4])) {
        touch($argv[4]);
        $deadline = microtime(true) + 10;
        while (! is_file($argv[5])) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Draft worker barrier timeout');
            }
            usleep(10000);
        }
    }
    if (! empty($argv[6])) {
        $intent = $argv[6];
        DB::connection()->beforeExecuting(function ($sql, $bindings, $connection) use ($intent) {
            if ($connection->transactionLevel() > 0 && str_contains($sql, 'horarios_semanales') && ! is_file($intent)) {
                file_put_contents($intent, json_encode(['writes' => ! preg_match('/^\s*select\b/i', $sql)]));
            }
        });
    }
    try {
        $draft = app(ScheduleDrafts::class)->save($payload['draft_id'], $payload['semana_id'], $payload['version'], $payload['request_id'], $payload);
        echo json_encode(['status' => 'saved', 'version' => $draft->version]);
    } catch (HttpExceptionInterface $conflict) {
        echo json_encode(['status' => $conflict->getStatusCode() === 409 ? 'conflict' : 'error', 'http_status' => $conflict->getStatusCode()]);
    } catch (Throwable $error) {
        echo json_encode(['status' => 'error', 'class' => get_class($error), 'message' => $error->getMessage()]);
    }
}
