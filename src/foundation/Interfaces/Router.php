<?php

namespace SwooleBase\Foundation\Interfaces;

use SwooleBase\Foundation\Http\RouteCollector;

interface Router
{
    public function __construct(RouteCollector $router);
}
