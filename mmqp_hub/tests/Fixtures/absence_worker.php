<?php

use App\Models\User;
use App\Services\AbsenceService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$app['config']->set('database.default', 'sqlite');
$app['config']->set('database.connections.sqlite.database', $argv[1]);
$app['config']->set('database.connections.sqlite.busy_timeout', 5000);
DB::purge('sqlite');
touch($argv[8].'.ready.'.$argv[9]);
$deadline = microtime(true) + 10;
while (! file_exists($argv[8]) && microtime(true) < $deadline) {
    usleep(10000);
}
if (! file_exists($argv[8])) {
    throw new RuntimeException('Concurrency test barrier timeout');
}
try {
    $actor = User::findOrFail((int) $argv[2]);
    $service = app(AbsenceService::class);
    $data = ['user_id' => (int) $argv[3], 'fecha' => $argv[7], 'tipo' => 'vacaciones', 'request_key' => $argv[6]];
    $absence = $argv[4] === 'create' ? $service->create($actor, $data)
        : $service->edit($actor, (int) $argv[5], 1, $data);
    echo json_encode(['status' => 200, 'id' => $absence->id]);
} catch (HttpExceptionInterface $error) {
    echo json_encode(['status' => $error->getStatusCode()]);
}
