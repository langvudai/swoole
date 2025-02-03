<?php

namespace SwooleBase\Foundation\Interfaces;

use SwooleBase\Foundation\DI;

interface RouteHandlerInterface
{
    public function __construct(array $route_found, string $request_class, object $request, string $response_class, object $response, DI $di);

    public function getMiddleware(): array;

    public function handle(): mixed;
}
