#!/usr/bin/env php
<?php

declare(strict_types=1);
defined('SWOOLE_VERSION') || define('SWOOLE_VERSION', '0.0');

use Swoole\Coroutine\Http\Client;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Process;
use SwooleBase\Foundation\Console;

//use Swoole\Coroutine\Http\Server as CoroutineHttpServer;
/** @var null|Console $console */
$console = require_once __DIR__ . '/src/bootstrap.php';

function startHttpServer(Console $console)
{
    $http = new Server($console->http_host, $console->http_port);
    $console->swooleServer($http, function (array $data, string $host, int $port, ?callable $callback, array $headers = []) {
        go(function () use ($data, $port, $host, $callback, $headers) {
            $client = new Client($host, $port);
            $client->setHeaders(array_merge([
                'Content-Type' => 'application/json',
                'User-Agent' => 'SwCoHttpClient',
            ], $headers));
            $client->post('/', json_encode($data));
            $response = $client->body;
            $client->close();
            $client = null;

            if ($callback) {
                call_user_func($callback, $response);
            }
        });
    });

    $http->set([
        'enable_static_handler' => $console->enable_static_handler ?? true,
        'document_root' => $console->document_root ?? (__DIR__ . '/html'),
        'max_request' => $console->max_request ?? 1000,
        'worker_num' => $console->worker_num ?? 5,
        'dispatch_mode' => $console->dispatch_mode ?? 2,
        'heartbeat_check_interval' => $console->heartbeat_check_interval ?? 30,
        'heartbeat_idle_time' => $console->heartbeat_idle_time ?? 120,
        'log_file' => $console->log_file ?? 'swoole.log',
        'backlog' => $console->backlog ?? 128,
        'pid_file' => $console->pid_file ?? 'swoole.pid'
    ]);

    $http->on(
        "request",
        function (Request $request, Response $response) use ($console, $http) {
            $date = date('Y-m-d');
            $time = date('H:i:s');
            file_put_contents("swoole-http.log", "[$date $time] swoole.INFO Swoole HTTP server on request " . PHP_EOL . json_encode($request) . PHP_EOL, FILE_APPEND);

            try {
                $path = $request->server['request_uri'];

                // return file /dist
                if (str_starts_with($path, '/dist/')) {
                    $file = __DIR__ . '/html' . $path;
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
                $content = "{$e->getMessage()}\n{$e->getFile()}: {$e->getLine()}\n\n{$e->getTraceAsString()}" . print_r($e->getTrace(), true);
                file_put_contents("swoole-{$date}.log", "[$date $time] swoole.ERROR Swoole HTTP server on request \n $content" . PHP_EOL, FILE_APPEND);
                $response->header("Content-Type", "text/plain");
                $response->end($e->getMessage() . ' ' . $e->getTraceAsString());
            }

        }
    );

    $console->onBeforeStart($http);

    $server_events = $console->accessible([$console, '$server_events']);

    if (!isset($server_events['start'])) {
        $http->on('start', function (Server $http) use ($console) {
            $date = date('Y-m-d');
            $time = date('H:i:s');
            return file_put_contents("swoole-http.log", "[$date $time] swoole.INFO Swoole HTTP server is started []" . PHP_EOL, FILE_APPEND);
        });
    }

    $http->start();
}

function startEventServer(Console $console)
{
    $sse = new Server($console->sse_host, $console->sse_port);
    $clients = [];

    $sse->on("Request", function (Request $request, Response $response) use (&$clients) {
        $response->header("access-control-allow-methods", "*");
        $response->header("access-control-allow-headers", "*");
        $response->header("access-control-allow-origin", "*");

        if ($request->server['request_uri'] === '/is') {
            $response->header("Content-Type", "text/plain");
            $response->end('It Work');
        } elseif ($request->server['request_method'] === 'GET') {
            $response->header("Content-Type", "text/event-stream");
            $response->header("Cache-Control", "no-cache");
            $response->header("Connection", "keep-alive");

            $clients[$request->fd] = $response;
            $response->write("data: #SSE connection established\n\n");

        } elseif ($request->server['request_method'] === 'POST') {
            $data = $request->rawContent();
            json_decode($data);

            if (json_last_error() == JSON_ERROR_NONE) {
                foreach ($clients as $fd => $client) {
                    $client->write("data: $data\n\n");
                }
            }
        }
    });

    $sse->on('close', function ($server, $fd) use (&$clients) {
        unset($clients[$fd]);
    });

    $sse->start();
}

function startServers(Console $console)
{
    $processes = [];

    if ($console->http_host) {
        $processes[] = new Process(function (Process $process) use ($console) {
            startHttpServer($console);
        });
    }

    if ($console->sse_host) {
        $processes[] = new Process(function (Process $process) use ($console) {
            startEventServer($console);
        });
    }

    foreach ($processes as $process) {
        $process->start();
    }

    for ($i = 0; $i < count($processes); $i++) {
        Process::wait();
    }
}

startServers($console);
