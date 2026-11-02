<?php

// Isolated-process integration fixture. Never connects to the application database.
use App\Models\CuadreCaja;
use App\Models\CuadreRevision;
use App\Models\User;
use App\Services\CuadreConflict;
use App\Services\SaveCuadre;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$mysql = getenv('CUADRE_TEST_MYSQL') === '1';
if ($mysql) {
    if (getenv('DB_DATABASE') !== 'mmqp_cuadre_concurrency') {
        throw new RuntimeException('MySQL concurrency tests require the dedicated mmqp_cuadre_concurrency database.');
    }
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'mmqp_cuadre_concurrency', 'database.connections.mysql.url' => null]);
} else {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[1], 'database.connections.sqlite.url' => null,
        'database.connections.sqlite.busy_timeout' => 10000]);
}
config(['cache.default' => 'array']);
DB::purge();
$mode = $argv[2];
if ($mode === 'init') {
    Artisan::call($mysql ? 'migrate:fresh' : 'migrate', ['--force' => true]);
    User::factory()->create(['id' => 1, 'es_admin' => true, 'dni' => '01234567A', 'email' => 'cuadre-test@example.test', 'nombre' => 'QA Cuadre']);
    echo json_encode(['ready' => true]);
} elseif ($mode === 'inspect') {
    echo json_encode(['closures' => CuadreCaja::count(), 'revisions' => CuadreRevision::count(), 'version' => CuadreCaja::first()?->version]);
} else {
    $payload = json_decode(file_get_contents($argv[3]), true, 512, JSON_THROW_ON_ERROR);
    if (isset($argv[4])) {
        touch($argv[4].'.ready');
        $deadline = microtime(true) + 15;
        while (! file_exists($argv[5])) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Concurrency barrier timeout');
            }
            usleep(10000);
        }
    }
    try {
        $revision = app(SaveCuadre::class)->save($payload, User::findOrFail(1));
        echo json_encode(['status' => 'saved', 'version' => $revision->version]);
    } catch (CuadreConflict $conflict) {
        echo json_encode(['status' => 'conflict', 'version' => $conflict->currentVersion]);
    }
}
