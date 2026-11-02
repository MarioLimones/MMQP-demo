<?php

namespace Tests\Feature;

use App\Models\SolicitudAusencia;
use App\Models\User;
use App\Services\AbsenceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AbsenceConcurrencyTest extends TestCase
{
    private string $databaseFile;

    private string $barrier;

    private User $admin;

    private User $employee;

    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $this->databaseFile = $directory.'/absence-concurrency-'.Str::uuid().'.sqlite';
        $this->barrier = $this->databaseFile.'.barrier';
        touch($this->databaseFile);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->databaseFile,
            'database.connections.sqlite.busy_timeout' => 5000]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
        $this->admin = User::factory()->create(['es_admin' => true]);
        $this->employee = User::factory()->create();
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        DB::disconnect('sqlite');
        foreach ([$this->databaseFile, $this->databaseFile.'-wal', $this->databaseFile.'-shm', $this->barrier,
            $this->barrier.'.ready.0', $this->barrier.'.ready.1'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function race(string $operation, int $id, string $firstDate, string $secondDate): array
    {
        $key = (string) Str::uuid();
        foreach ([$firstDate, $secondDate] as $index => $date) {
            $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/absence_worker.php'), $this->databaseFile,
                (string) $this->admin->id, (string) $this->employee->id, $operation, (string) $id, $key, $date,
                $this->barrier, (string) $index], base_path(), ['APP_ENV' => 'testing'], timeout: 15);
            $worker->start();
            $this->workers[] = $worker;
        }
        $deadline = microtime(true) + 10;
        while ((! is_file($this->barrier.'.ready.0') || ! is_file($this->barrier.'.ready.1')) && microtime(true) < $deadline) {
            usleep(10000);
        }
        $this->assertFileExists($this->barrier.'.ready.0');
        $this->assertFileExists($this->barrier.'.ready.1');
        touch($this->barrier);
        $results = [];
        foreach ($this->workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $results[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        }

        return $results;
    }

    public function test_simultaneous_identical_submissions_have_one_record(): void
    {
        $results = $this->race('create', 0, '2026-11-02', '2026-11-02');
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(1, SolicitudAusencia::count());
        $this->assertSame(['2026-11-02'], $this->employee->fresh()->fechas_libres_solicitadas);
    }

    public function test_simultaneous_edits_only_accept_one_version(): void
    {
        $absence = app(AbsenceService::class)->create($this->admin, ['user_id' => $this->employee->id,
            'fecha' => '2026-11-01', 'tipo' => 'vacaciones', 'request_key' => (string) Str::uuid()]);
        $results = $this->race('edit', $absence->id, '2026-11-02', '2026-11-03');
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
        $this->assertSame(2, $absence->fresh()->version);
        $this->assertSame([$absence->fresh()->fecha->format('Y-m-d')], $this->employee->fresh()->fechas_libres_solicitadas);
        $this->assertSame(1, SolicitudAusencia::count());
    }

    public function test_simultaneous_different_payloads_cannot_reuse_a_key(): void
    {
        $results = $this->race('create', 0, '2026-11-02', '2026-11-03');
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
        $this->assertSame(1, SolicitudAusencia::count());
        $this->assertCount(1, $this->employee->fresh()->fechas_libres_solicitadas);
    }
}
