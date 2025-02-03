<?php

namespace SwooleBase\Foundation\Traits;

trait IsBitwise
{
    /**
     * @return array
     * @throws \ReflectionException
     */
    public static function toArray(): array
    {
        $class = static::class;
        if (!array_key_exists($class, static::$cache)) {
            $reflection = new \ReflectionClass($class);
            static::$cache[$class] = array_filter($reflection->getConstants(), function ($x) {
                return is_int($x) && (($x & ($x - 1)) === 0);
            });
        }

        return static::$cache[$class];
    }

    /**
     * @param array $values
     * @return int
     * @throws \ReflectionException
     */
    public static function compact(array $values): int
    {
        $def = static::toArray();
        return (int)array_sum(array_filter($values, function ($x) use ($def) {
            return in_array($x, $def, true);
        }));
    }

    /**
     * @param $number
     * @return array
     * @throws \ReflectionException
     */
    public static function extract($number): array
    {
        $items = static::toArray();
        return array_filter($items, function ($x) use ($number) {
            return $x & $number;
        });
    }

    /**
     * @param $number
     * @return bool
     * @throws \ReflectionException
     */
    public function validate($number): bool
    {
        return in_array($this->value, static::extract($number), true);
    }
}
