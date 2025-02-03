<?php

namespace SwooleBase\Foundation\Interfaces;

interface Storable
{
    /**
     * @param string $action
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $action, array $arguments): mixed;

    /**
     * @param string $name
     */
    public function __get(string $name);
}
