<?php

namespace SwooleBase\Foundation\Abstracts;

use SwooleBase\Foundation\Interfaces\SessionInterface;

abstract class Session implements SessionInterface
{
    /** @var array|Session[] */
    private static array $cabinet = [];

    public static function createOrReceive(callable $factory, $fd, $array_headers = [], $string_token = ''): ?Session
    {
        if (!$array_headers || !is_array($array_headers)) {
            $array_headers = [];
        }

        $arr = array_filter($array_headers, fn ($key) => in_array(strtolower($key), ['user-agent', 'accept', 'accept-encoding']), ARRAY_FILTER_USE_KEY);
        $arr['fd'] = $fd;
        $arr['token'] = (string)$string_token;
        ksort($arr);
        $drawer = md5(json_encode($arr));

        if (!isset(self::$cabinet[$drawer]) || !(self::$cabinet[$drawer] instanceof Session)) {
            self::$cabinet[$drawer] = $factory($arr);
        }

        return self::$cabinet[$drawer] ?? null;
    }
}
