<?php

namespace SwooleBase\Foundation\Traits;

use SwooleBase\Foundation\Exception;

trait MacroAble
{
    /**
     * @var array
     */
    protected static $macros = [];

    public static function macro(string $name, callable $macro)
    {
        self::$macros[$name] = $macro;
    }

    public static function hasMacro(string $name): bool
    {
        return isset(self::$macros[$name]);
    }

    public function __call($method, $parameters)
    {
        if (self::hasMacro($method)) {
            return call_user_func_array(self::$macros[$method], [$this, ...$parameters]);
        }

        throw new Exception("Method {$method} does not exist.", ['MacroAble', $method]);
    }

    public static function __callStatic($method, $parameters)
    {
        if (self::hasMacro($method)) {
            return call_user_func_array(self::$macros[$method], $parameters);
        }

        throw new Exception("Static method {$method} does not exist.", ['MacroAble', $method]);
    }
}
