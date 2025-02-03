<?php

namespace SwooleBase\Foundation\Abstracts;

abstract class Storable implements \SwooleBase\Foundation\Interfaces\Storable
{
    private static array $storable = [];

    /**
     * create store object
     * if exists object will refresh time life
     */
    public static function createIfNotExits()
    {
        if (!self::$storable[static::class]) {
            $obj = new static();
            self::$storable[static::class] = [$obj, time()];
        } else {
            [$obj] = self::$storable[static::class];
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

        if (!$obj || (static::duration() && time() >= static::duration() + $created)) {
            $obj = new static();
            self::$storable[static::class] = [$obj, time()];
        }

        return call_user_func_array([$obj, $name], $arguments);
    }

    /**
     * @return int
     */
    public static function duration(): int
    {
        return 0;
    }
}
