<?php

namespace SwooleBase\Foundation\Interfaces;

interface SessionInterface 
{
    /**
     * Checks if an attribute is defined.
     */
    public function has(string $name): bool;

    /**
     * Returns an attribute.
     */
    public function get(string $name, mixed $default = null): mixed;

    /**
     * Sets an attribute.
     *
     * @return void
     */
    public function set(string $name, mixed $value);

    /**
     * Clears all attributes.
     *
     * @return void
     */
    public function clear();

}
