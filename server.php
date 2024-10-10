#!/usr/bin/env php
<?php

declare(strict_types=1);
defined('SWOOLE_VERSION') || define('SWOOLE_VERSION', '0.0');

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
//use Swoole\Coroutine\Http\Server as CoroutineHttpServer;
/** @var null|SwooleBase\Foundation\Console $console */
$console = require_once __DIR__ . '/src/bootstrap.php';

$http = new Server("0.0.0.0", 9501);
$console->swooleServer($http);

$http->set($console->serverSettings([
    'enable_static_handler' => true,
    'document_root' => __DIR__.'/html',
    'max_request' => 1000,
    'worker_num' => 5,
    'dispatch_mode' => 2,
    'heartbeat_check_interval' => 30,
    'heartbeat_idle_time' => 120,
    'log_file' => 'swoole.log',
    'backlog' => 128,
    'pid_file' => 'swoole.pid'
]));

$http->on(
    "request",
    function (Request $request, Response $response) use ($console) {
        $date = date('Y-m-d');
        $time = date('H:i:s');
        file_put_contents("swoole-http.log", "[$date $time] swoole.INFO Swoole HTTP server on request ".PHP_EOL.json_encode($request).PHP_EOL, FILE_APPEND);

        try {
            $path = $request->server['request_uri'];

            // return file /dist
            if (str_starts_with($path, '/dist/')) {
                $file = __DIR__.'/html' . $path;
                if (is_file($file)) {
                    $response->sendfile($file);
                    return;
                }
            }

            $result = isset($console) ? $console->dispatch($request) : null;

            if ($result instanceof SwooleBase\Foundation\Interfaces\ResponseInterface) {
                $content = $result->getContent();
                $headers = $result->getHeaders();

                if (is_array($headers)) {
                    foreach ($headers as $key => $arr_val) {
                        $response->header($key, implode(', ', $arr_val));
                    }
                }

                $response->end($content);
            } else {
                $response->header("Content-Type", "text/plain");
                $response->header("access-control-allow-methods", "*");
                $response->header("access-control-allow-headers", "*");
                $response->header("access-control-allow-origin", "*");

                $response->end('500 - Internal Server Error');
            }

        } catch (\Throwable $e) {
            $content = "{$e->getMessage()}\n{$e->getFile()}: {$e->getLine()}\n\n{$e->getTraceAsString()}".print_r($e->getTrace(), true);
            file_put_contents("swoole-{$date}.log", "[$date $time] swoole.ERROR Swoole HTTP server on request \n $content" . PHP_EOL, FILE_APPEND);
            $response->header("Content-Type", "text/plain");
            $response->end($e->getMessage(). ' ' .$e->getTraceAsString());
        }

    }
);

$console->onBeforeStart($http);

$server_events = $console->accessible([$console, '$server_events']);

if (!isset($server_events['start'])) {
    $http->on( 'start', function (Server $http) use ($console) {
        $date = date('Y-m-d');
        $time = date('H:i:s');
        return file_put_contents("swoole-http.log", "[$date $time] swoole.INFO Swoole HTTP server is started []" . PHP_EOL, FILE_APPEND);
    });
}

$http->start();
