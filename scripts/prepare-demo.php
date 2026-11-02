<?php

declare(strict_types=1);

// Cross-platform local installation. Never accepts an external database path.
$root = dirname(__DIR__);
$hub = $root . '/mmqp_hub';
$envPath = $hub . '/.env';
$database = str_replace('\\', '/', $hub . '/database/demo.sqlite');

function stopDemoSetup(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!is_file($hub . '/vendor/autoload.php')) {
    stopDemoSetup('Primero ejecuta composer install desde mmqp_hub.');
}

if (is_file($envPath)) {
    $env = file_get_contents($envPath);
    if (!preg_match('/^DEMO_SETUP=1\s*$/m', $env)
        || !preg_match('/^DEMO_MODE=true\s*$/m', $env)
        || !preg_match('/^DB_CONNECTION=sqlite\s*$/m', $env)) {
        stopDemoSetup('El .env no está identificado como instalación demo. Usa una carpeta nueva.');
    }
    if (!preg_match('/^DB_DATABASE="?' . preg_quote($database, '/') . '"?\s*$/m', $env)) {
        stopDemoSetup('El .env no apunta a database/demo.sqlite de esta copia. Usa una carpeta nueva.');
    }
} else {
    if (is_file($database)) {
        stopDemoSetup('Ya hay una base demo sin su configuración. Usa una carpeta nueva.');
    }
    $env = file_get_contents($hub . '/.env.example');
    $key = 'base64:' . base64_encode(random_bytes(32));
    $env = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $env);
    $env = preg_replace('/^DB_DATABASE=.*$/m', 'DB_DATABASE="' . $database . '"', $env);

    $python = $root . (PHP_OS_FAMILY === 'Windows' ? '/algoritmo/venv/Scripts/python.exe' : '/algoritmo/venv/bin/python');
    if (is_file($python)) {
        $python = str_replace('\\', '/', $python);
        $env = preg_replace('/^PYTHON_PATH=.*$/m', 'PYTHON_PATH="' . $python . '"', $env);
        $env = preg_replace('/^CONSUMO_PDF_PYTHON=.*$/m', 'CONSUMO_PDF_PYTHON="' . $python . '"', $env);
    }

    if (file_put_contents($envPath, $env, LOCK_EX) === false) {
        stopDemoSetup('No se ha podido crear .env.');
    }
}

foreach ([
    'storage/app/private', 'storage/app/public', 'storage/framework/cache/data',
    'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache',
] as $relative) {
    if (!is_dir($hub . '/' . $relative) && !mkdir($hub . '/' . $relative, 0775, true)) {
        stopDemoSetup('No se ha podido crear el almacenamiento de la demo.');
    }
}

if (!is_file($database) && !touch($database)) {
    stopDemoSetup('No se ha podido crear database/demo.sqlite.');
}

// Override inherited shell DB variables for these child processes.
foreach ([
    'DEMO_MODE' => 'true', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database,
    'DB_URL' => '', 'APP_DEBUG' => 'false', 'MAIL_MAILER' => 'array',
    'CACHE_STORE' => 'file', 'SESSION_DRIVER' => 'file', 'QUEUE_CONNECTION' => 'sync',
    'FILESYSTEM_DISK' => 'local', 'BROADCAST_CONNECTION' => 'log', 'LOG_CHANNEL' => 'single',
    'LARAVEL_STORAGE_PATH' => $hub . '/storage',
    'VIEW_COMPILED_PATH' => $hub . '/storage/framework/views',
    // Laravel resolves these relative to base_path; drive-letter paths are not
    // recognized as absolute by normalizeCachePath on Windows.
    'APP_CONFIG_CACHE' => 'bootstrap/cache/demo-setup-config.php',
    'APP_SERVICES_CACHE' => 'bootstrap/cache/services.php',
    'APP_PACKAGES_CACHE' => 'bootstrap/cache/packages.php',
    'APP_ROUTES_CACHE' => 'bootstrap/cache/routes-v7.php',
    'APP_EVENTS_CACHE' => 'bootstrap/cache/events.php',
] as $name => $value) {
    putenv($name . '=' . $value);
}

foreach ([['migrate', '--force', '--no-interaction'], ['db:seed', '--force', '--no-interaction']] as $arguments) {
    $process = proc_open([PHP_BINARY, 'artisan', ...$arguments], [STDIN, STDOUT, STDERR], $pipes, $hub);
    if (!is_resource($process) || proc_close($process) !== 0) {
        stopDemoSetup('La preparación se detuvo. Revisa el error anterior antes de iniciar la demo.');
    }
}

echo 'Demo preparada en su propia base SQLite. Inicia Laravel desde mmqp_hub.' . PHP_EOL;
