<?php

namespace SwooleBase\Foundation\Abstracts;

abstract class Storable implements \SwooleBase\Foundation\Interfaces\Storable
{
    private static array $storable = [];

    public static function createIfNotExits()
    {
        if (!self::$storable[static::class]) {
            $obj = new static();
            self::$storable[static::class] = [$obj, time()];
        }
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        /** @var static $obj */
        [$obj, $created] = array_pad(self::$storable[static::class] ?? [], 2, null);

        if (!$obj || ($obj->duration() && time() >= $obj->duration() + $created)) {
            $obj = new static();
            self::$storable[static::class] = [$obj, time()];
        }

        return call_user_func_array([$obj, $name], $arguments);
    }

    /**
     * @param string $action
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $action, array $arguments): mixed
    {
        if (isset($this->actions[$action])) {
            return call_user_func_array($this->actions[$action], $arguments);
        } elseif (method_exists($this, "{$action}Action")) {
            return call_user_func_array([$this, "_$action"], $arguments);
        }

        return null;
    }

    /**
     * @return int
     */
    public function duration(): int
    {
        return 0;
    }

    /**
     * @param string $name
     */
    abstract public function __get(string $name);

}
