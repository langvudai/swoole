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

    /**
     * The key that should be mutated to dates
     * [key, key.sub_key, key.*.sub_key, key.sub_key.*.sub_sub_key]
     *
     * @var array
     */
    protected array $dates = [];

    /**
     * @var string
     */
    protected string $date_format = '';

    /**
     * The key excluded from the array.
     *
     * @var array
     */
    protected array $hidden = [];

    /**
     * @param string $name
     * @return null|mixed
     */
    public function __get(string $name)
    {
        if (isset($this->data[$name])) {
            if (in_array($name, $this->dates) || array_key_exists($name, $this->dates)) {
                return $this->formatDate($name, $this->data[$name]);
            }

            return $this->data[$name];
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
     * @return array|mixed
     */
    public function jsonSerialize()
    {
        return $this->convertKeysToCamelCase($this->data);
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
     * @param array $data
     * @return array
     */
    private function convertKeysToCamelCase(array $data): array
    {
        $keys = array_diff(array_keys($data), $this->hidden);
        $array = [];

        foreach ($keys as $key) {
            $key_camel = preg_replace_callback('/[^a-z0-9]([a-z0-9])/', fn($matches) => strtoupper($matches[1]), $key);

            if (in_array($key, $this->dates) || array_key_exists($key, $this->dates)) {
                $array[$key_camel] = $this->formatDate($key, $data[$key]);
            } else {
                $array[$key_camel] = is_array($data[$key]) ? $this->subTransform($data[$key], $key) : $data[$key];
            }
        }

        return $array;
    }

    /**
     * @param $name
     * @param $value
     * @return string|null
     */
    private function formatDate($name, $value): ?string
    {
        /** @var Carbon|null $date */
        $date = ($value instanceof Carbon) ? $value : (is_string($value) ? Carbon::parse($value) : null);

        if ($date && $this->date_format) {
            return $date->format($this->date_format);
        }

        return !$date ? null : $date->toAtomString();
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
            $key_camel = snake_to_camel($key);
            $key = $prefix .'.'. preg_replace('/^\d+$/', '*', $key);

            if (in_array($key, $this->dates) || array_key_exists($key, $this->dates)) {
                $array[$key_camel] = $this->formatDate($key, $value);
            } else {
                $array[$key_camel] = is_array($value) ? $this->subTransform($value, $key) : $value;
            }
        }

        return $array;
    }
}
