<?php

namespace SwooleBase\Foundation\Abstracts;

use Carbon\Carbon;
use Countable;
use JsonSerializable;
use Stringable;

abstract class Json implements JsonSerializable, Stringable, Countable
{
    /** @var array */
    protected array $data = [];

    /** @var array */
    protected array $json_data = [];

    /**
     * The key excluded from the array.
     *
     * @var array
     */
    protected array $hidden = [];

    final public function resetJsonData()
    {
        $this->json_data = [];
    }

    /**
     * @param string $name
     * @return null|mixed
     */
    public function __get(string $name)
    {
        if (isset($this->data[$name])) {
            return $this->gatherData($name, $this->data[$name]) ?? $this->data[$name];
        }

        return null;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    /**
     * @param string $name
     * @param $value
     */
    public function __set(string $name, $value): void
    {
        false;
    }

    /**
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        if (empty($this->json_data)) {
            $this->json_data = $this->convertKeysToCamelCase($this->data);
        }

        return $this->json_data;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return json_encode($this);
    }

    /**
     * The key of the data to be transformed
     * [key, key.sub_key, key.*.sub_key, key.sub_key.*.sub_sub_key]
     *
     * @return array
     */
    public static function transform(): array
    {
        return [];
    }

    /**
     * @param $value
     * @param string $format
     * @return string|null
     */
    protected static function formatDate($value, $format = ''): ?string
    {
        /** @var Carbon|null $date */
        $date = ($value instanceof Carbon) ? $value : (is_string($value) ? Carbon::parse($value) : null);

        if ($date && $format) {
            return $date->format($format);
        }

        return !$date ? null : $date->toAtomString();
    }

    protected function gatherData(string $name, $data): mixed
    {
        if (isset(static::transform()[$name])) {
            $callable = static::transform()[$name];

            if (is_callable($callable)) {
                return call_user_func($callable, $data);
            }

            if (is_callable([$this, $callable])) {
                return call_user_func([$this, $callable], $data);
            }
        }

        return null;
    }

    /**
     * @param array $data
     * @return array
     */
    private function convertKeysToCamelCase(array $data): array
    {
        $keys = array_diff(array_keys($data), $this->hidden);
        $array = [];

        foreach ($keys as $key) {
            $key_camel = preg_replace_callback('/[^a-z0-9]([a-z0-9])/', fn($matches) => strtoupper($matches[1]), $key);
            $array[$key_camel] = $this->gatherData($key, $data[$key]) ?? (is_array($data[$key]) ? $this->subTransform($data[$key], $key) : $data[$key]);
        }

        return $array;
    }

    /**
     * @param array $arr
     * @param string $prefix
     * @return array
     */
    private function subTransform(array $arr, string $prefix): array
    {
        $array = [];

        foreach ($arr as $key => $value) {
            $key_camel = preg_replace_callback('/[^a-z0-9]([a-z0-9])/', fn($matches) => strtoupper($matches[1]), $key);
            $key = $prefix .'.'. preg_replace('/^\d+$/', '*', $key);
            $array[$key_camel] = $this->gatherData($key, $value) ?? (is_array($value) ? $this->subTransform($value, $key) : $value);
        }

        return $array;
    }
}
