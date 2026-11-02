<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class CuadreConcurrencyTest extends TestCase
{
    private string $directory;

    private string $database;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mmqp-cuadre-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->database = $this->directory.DIRECTORY_SEPARATOR.'test.sqlite';
        touch($this->database);
        $this->runWorker(['init']);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function test_simultaneous_retries_create_one_effect_and_simultaneous_edits_detect_conflict(): void
    {
        $payload = ['fecha' => '2026-10-10', 'turno' => 'cierre', 'billetes' => ['100' => 4], 'monedas' => [],
            'billetes_ingreso' => ['100' => 1], 'monedas_ingreso' => [], 'blisters' => ['total' => '0'], 'nota_blanca' => '0',
            'fondo_fijo' => '300', 'dni_empleado' => '01234567', 'expected_version' => 0, 'request_id' => (string) Str::uuid()];
        $results = $this->race($payload, $payload);
        $this->assertSame(['saved', 'saved'], array_column($results, 'status'));
        $this->assertSame(['closures' => 1, 'revisions' => 1, 'version' => 1], $this->runWorker(['inspect']));
        $first = array_replace($payload, ['expected_version' => 1, 'request_id' => (string) Str::uuid(), 'nota_blanca' => '0.10']);
        $second = array_replace($first, ['request_id' => (string) Str::uuid(), 'nota_blanca' => '0.20']);
        $results = $this->race($first, $second);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame(['conflict', 'saved'], $statuses);
        $this->assertSame(['closures' => 1, 'revisions' => 2, 'version' => 2], $this->runWorker(['inspect']));
    }

    private function race(array $first, array $second): array
    {
        $go = $this->directory.DIRECTORY_SEPARATOR.bin2hex(random_bytes(4)).'.go';
        $processes = [];
        $signals = [];
        foreach ([$first, $second] as $index => $payload) {
            $file = $this->directory.DIRECTORY_SEPARATOR.bin2hex(random_bytes(4)).'.json';
            file_put_contents($file, json_encode($payload, JSON_THROW_ON_ERROR));
            $signal = $file.'.signal';
            $signals[] = $signal.'.ready';
            $processes[] = $process = $this->worker(['save', $file, $signal, $go]);
            $process->start();
        }
        $deadline = microtime(true) + 15;
        while (! file_exists($signals[0]) || ! file_exists($signals[1])) {
            if (microtime(true) > $deadline) {
                foreach ($processes as $process) {
                    $process->stop();
                }
                $this->fail('Workers did not reach the concurrency barrier');
            }
            usleep(10000);
        }
        touch($go);

        return array_map(function ($process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

            return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        }, $processes);
    }

    private function worker(array $arguments): Process
    {
        return new Process([PHP_BINARY, __DIR__.'/../Support/cuadre_worker.php', $this->database, ...$arguments], dirname(__DIR__, 2), ['APP_ENV' => 'testing'], timeout: 30);
    }

    private function runWorker(array $arguments): array
    {
        $process = $this->worker($arguments);
        $process->mustRun();

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
