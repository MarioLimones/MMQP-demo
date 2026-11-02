<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ScheduleDraftConcurrencyTest extends TestCase
{
    private string $directory;

    private string $database;

    private array $initial;

    private array $workers = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mmqp-draft-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->database = $this->directory.DIRECTORY_SEPARATOR.'test.sqlite';
        touch($this->database);
        $this->initial = $this->runWorker(['init']);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function test_a_save_waits_for_an_unrelated_sqlite_writer_without_an_upgrade_deadlock(): void
    {
        $writer = new PDO('sqlite:'.$this->database);
        $writer->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $writer->beginTransaction();
        $writer->exec('UPDATE horarios_semanales SET version = version WHERE 1 = 0');
        $intent = $this->directory.DIRECTORY_SEPARATOR.'first-query.json';
        $file = $this->payloadFile($this->payload('Waiting save'));
        $process = $this->worker(['save', $file, '', '', $intent]);
        $process->start();
        $this->workers[] = $process;
        try {
            $this->waitFor(fn () => is_file($intent) || ! $process->isRunning(), 'Save did not reach its transaction');
            $this->assertFileExists($intent, $process->getErrorOutput().$process->getOutput());
            $firstQuery = json_decode(file_get_contents($intent), true, 512, JSON_THROW_ON_ERROR);
            if ($firstQuery['writes']) {
                // A reserved writer can finish before the save reads a snapshot.
                $writer->commit();
            }
            // For a deferred read-first save, retain the competing reservation until
            // its bounded retries finish. This deterministically exposes SQLITE_BUSY.
            $process->wait();
        } finally {
            if ($writer->inTransaction()) {
                $writer->rollBack();
            }
        }
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('saved', $result['status'], json_encode($result));
        $stored = $this->runWorker(['inspect']);
        $this->assertSame(2, $stored['version']);
        $this->assertSame('Waiting save', $stored['note']);
        $this->assertSame($this->initial['publication_hash'], $stored['publication_hash']);
        $this->assertSame('borrador', $stored['state']);
        $this->assertFalse($stored['current']);
    }

    public function test_concurrent_retries_have_one_effect_and_divergent_saves_keep_one_version(): void
    {
        $payload = $this->payload('Identical retry');
        $results = $this->race(array_fill(0, 5, $payload));
        $this->assertSame(array_fill(0, 5, 'saved'), array_column($results, 'status'));
        $this->assertSame(array_fill(0, 5, 2), array_column($results, 'version'));
        $stored = $this->runWorker(['inspect']);
        $this->assertSame(1, $stored['drafts']);
        $this->assertSame(2, $stored['version']);
        $this->assertSame('Identical retry', $stored['note']);

        $payloads = [];
        for ($i = 0; $i < 5; $i++) {
            $payloads[] = $this->payload('Writer '.$i, 2);
        }
        $results = $this->race($payloads);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame(['conflict', 'conflict', 'conflict', 'conflict', 'saved'], $statuses);
        $stored = $this->runWorker(['inspect']);
        $this->assertSame(3, $stored['version']);
        $winner = array_search('saved', array_column($results, 'status'), true);
        $this->assertSame('Writer '.$winner, $stored['note']);
        $this->assertSame(1, $stored['drafts']);
        $this->assertSame($this->initial['publication_hash'], $stored['publication_hash']);
        $this->assertSame('borrador', $stored['state']);
        $this->assertFalse($stored['current']);
    }

    private function payload(string $note, int $version = 1): array
    {
        return ['draft_id' => $this->initial['draft_id'], 'semana_id' => '2030-W01', 'version' => $version,
            'request_id' => (string) Str::uuid(), 'fecha_inicio' => '2029-12-31', 'fecha_fin' => '2030-01-06',
            'plantilla_diseno' => [['dia' => 'lunes', 'notas' => $note, 'turnos_requeridos' => [
                ['turno_id' => 'qa-shift', 'rol_necesario' => 'encargado', 'hora_inicio' => '12:00', 'hora_fin' => '16:00']]]],
            'empleados_snapshot' => [], 'resultados' => []];
    }

    private function payloadFile(array $payload): string
    {
        $file = $this->directory.DIRECTORY_SEPARATOR.bin2hex(random_bytes(8)).'.json';
        file_put_contents($file, json_encode($payload, JSON_THROW_ON_ERROR));

        return $file;
    }

    private function race(array $payloads): array
    {
        $go = $this->directory.DIRECTORY_SEPARATOR.bin2hex(random_bytes(8)).'.go';
        $processes = [];
        $signals = [];
        foreach ($payloads as $payload) {
            $file = $this->payloadFile($payload);
            $signal = $file.'.ready';
            $process = $this->worker(['save', $file, $signal, $go]);
            $process->start();
            $this->workers[] = $process;
            $processes[] = $process;
            $signals[] = $signal;
        }
        $this->waitFor(fn () => count(array_filter($signals, 'is_file')) === count($signals), 'Workers did not reach the concurrency barrier');
        touch($go);

        return array_map(function (Process $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

            return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        }, $processes);
    }

    private function waitFor(callable $ready, string $message): void
    {
        $deadline = microtime(true) + 10;
        while (! $ready()) {
            if (microtime(true) > $deadline) {
                $this->fail($message);
            }
            usleep(10000);
        }
    }

    private function worker(array $arguments): Process
    {
        return new Process([PHP_BINARY, __DIR__.'/../Support/schedule_draft_worker.php', $this->database, ...$arguments],
            dirname(__DIR__, 2), ['APP_ENV' => 'testing'], timeout: 20);
    }

    private function runWorker(array $arguments): array
    {
        $process = $this->worker($arguments);
        $process->mustRun();

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
