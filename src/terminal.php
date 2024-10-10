#!/usr/bin/env php
<?php
if (php_sapi_name() == 'cli' && isset($argv) && basename(__FILE__) == basename($argv[0])) {
    $serialized_file = $argv[1] ?? null;

    if (!$serialized_file) {
        exit();
    }


    require_once __DIR__.'/../vendor/autoload.php';

// load .env
    (function ($paths, $names = null, bool $shortCircuit = true, string $fileEncoding = null) {
        $dotenv = Dotenv\Dotenv::createImmutable($paths, $names, $shortCircuit, $fileEncoding);
        $dotenv->load();

        if (dotenv('APP_DEBUG')) {
            ini_set("display_errors",1);
            error_reporting(E_ALL);
        }

        return $dotenv;
    })(dirname(__DIR__));

    $console = new SwooleBase\Foundation\Console(dirname(__DIR__));
    $console->classAlias([
        'DI' => SwooleBase\Foundation\DI::class,
    ]);

    (function(string $driver, array $config) {
        $connection_name = $config['connection_name'] ?? $driver;
        Src\Database\PDO::config($connection_name, match ($driver) {
            Src\Database\Postgres\Driver::POSTGRESQL => Src\Database\Postgres\Driver::class,
            default => ''
        }, $config);
    })(Src\Database\PDO::POSTGRESQL, [
        'HOST' => dotenv('DB_HOST', '127.0.0.1'),
        'PORT' => dotenv('DB_PORT', 5432),
        'DATABASE' => dotenv('DB_DATABASE', 'postgres'),
        'USERNAME' => dotenv('DB_USERNAME', 'postgres'),
        'PASSWORD' => dotenv('DB_PASSWORD', 'postgres'),
        'SCHEMA' => dotenv('DB_SCHEMA', 'public'),
        'TIMEZONE' => dotenv('TIMEZONE', 'Asia/Ho_Chi_Minh'),
    ]);

    $console->terminal($serialized_file);
}
