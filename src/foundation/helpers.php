<?php

if (!function_exists('dotenv')) {
    /**
     * @param string $key
     * @param null $default
     * @return mixed
     */
    function dotenv(string $key, $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }
}

if (! function_exists('make')) {
    function make(string $abstract, array $arguments = null) {
        if (function_exists('dependency_injection')) {
            return dependency_injection($abstract, $arguments);
        }
        return null;
    }
}

if (!function_exists('base64_url_encode')) {
    /**
     * @param string $data
     * @return string
     */
    #[\JetBrains\PhpStorm\Pure]
    function base64_url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('base64_url_decode')) {
    /**
     * @param string $data
     * @return string
     */
    #[\JetBrains\PhpStorm\Pure]
    function base64_url_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}

if (!function_exists('pascal_case')) {
    /**
     * @param string $str
     * @return string
     */
    function pascal_case(string $str): string
    {
        return ucfirst(preg_replace_callback('/[^a-z]([a-z])/i', function ($matches) {
            return strtoupper($matches[1]);
        }, $str));
    }
}

if (!function_exists('snake_to_camel')) {
    /**
     * @param string $str
     * @return string
     */
    function snake_to_camel(string $str): string
    {
        return lcfirst(preg_replace_callback('/_([^_])/', function ($m) {
            return strtoupper($m[1]);
        }, strtolower($str)));
    }
}

if (!function_exists('pascal_case_to_snake_case')) {
    /**
     * @param string $str
     * @return string
     */
    function pascal_case_to_snake_case(string $str): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', lcfirst($str)));
    }
}

if (!function_exists('normalize_string')) {
    /**
     * @param string|null $str
     * @return string|null
     */
    function normalize_string(?string $str): ?string
    {
        $normalized = \normalizer_normalize($str, \Normalizer::FORM_C);

        if (\normalizer_is_normalized($normalized, \Normalizer::FORM_C)) {
            return $normalized;
        }

        return $str;
    }
}

if (!function_exists('convert_str')) {
    /**
     * @param $str
     * @return string|string[]|null
     */
    function convert_str($str)
    {
        $str = normalize_string($str);
        $str = str_replace('ền', 'en', $str);
        $str = preg_replace("/(ũ)/", "u", $str);
        $str = preg_replace("/(ằ|ả|ạ)/", "a", $str);
        $str = preg_replace("/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/", "a", $str);
        $str = preg_replace("/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/", "e", $str);
        $str = preg_replace("/(ì|í|ị|ỉ|ĩ)/", "i", $str);
        $str = preg_replace("/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/", "o", $str);
        $str = preg_replace("/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/", "u", $str);
        $str = preg_replace("/(ỳ|ý|ỵ|ỷ|ỹ)/", "y", $str);
        $str = preg_replace("/(đ)/", "d", $str);
        $str = preg_replace("/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/", "A", $str);
        $str = preg_replace("/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/", "E", $str);
        $str = preg_replace("/(Ì|Í|Ị|Ỉ|Ĩ)/", "I", $str);
        $str = preg_replace("/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/", "O", $str);
        $str = preg_replace("/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/", "U", $str);
        $str = preg_replace("/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/", "Y", $str);
        $str = preg_replace("/(Đ)/", "D", $str);
        return $str;
    }
}

if (!function_exists('array_get')) {
    /**
     * Get an item from an array using "dot" notation.
     *
     * @param $array
     * @param $key_path
     * @param null $default
     * @return mixed
     */
    function array_get($array, $key_path, $default = null)
    {
        if (!is_scalar($key_path)) {
            return $default;
        }

        $keys = explode('.', $key_path);

        foreach ($keys as $key) {
            if (is_array($array) && array_key_exists($key, $array)) {
                $array = $array[$key];
            } else {
                return $default;
            }
        }

        return $array;
    }
}

if (!function_exists('ampReplaceXML')) {
    /**
     * @param string|null $string $string
     * @return string
     */
    function ampReplaceXML(?string $string): string
    {
        return str_replace('&', '&amp;', $string ?? '');
    }
}

if (!function_exists('get_pg_date')) {
    /**
     * @param string|null $date
     * @param null $default
     * @return string|null
     */
    function get_pg_date(?string $date, $default = NULL): ?string
    {
        if (preg_match('/^(\d?\d)[\/\-\.\s](\d?\d)[\/\-\.\s](\d{4})$/', (string)$date, $matches)) {
            return "'{$matches[3]}-{$matches[2]}-{$matches[1]}'::DATE";
        }

        return $default;
    }
}

if (!function_exists('get_pg_escape_string')) {
    /**
     * @param string|null $string $string
     * @param string|null $cast
     * @return string
     */
    function get_pg_escape_string(?string $string, string $cast = null): string
    {
        return "'" . pg_escape_string($string ?? '') . ($cast ? "'::" . $cast : "'");
    }
}

if (!function_exists('get_pg_jsonb')) {
    /**
     * @param string|null $string $string
     * @param string|null $cast
     * @return string
     */
    function get_pg_jsonb(array $data): string
    {
        $keys   = array_keys($data);
        $values = array_map('pg_escape_string', array_values($data));
        return "'" . json_encode(array_combine($keys, $values)) . "'::JSONB";
    }
}

if (!function_exists('remove_non_printable_characters')) {
    /**
     * @param string $string
     * @return string
     */
    function remove_non_printable_characters(string $string): string
    {
        return (string)preg_replace('/[\x00-\x1F\x7F]/', '', $string);
    }
}

if (!function_exists('countSubarrayMatches')) {
    /**
     * @param array $multiArray
     * @param array $checkArray
     * @return int
     */
    function countSubarrayMatches(array $multiArray, array $checkArray): int
    {
        return array_reduce($multiArray, function ($carry, $subArray) use ($checkArray) {
            $matchPairs = array_intersect_assoc($subArray, $checkArray);
            return $carry + (count($matchPairs) === count($checkArray));
        }, 0);
    }
}

if (!function_exists('remove_control_character')) {
    /**
     * @param String|null $string
     * @return String|null
     */
    function remove_control_character(?String $string): ?string
    {
        if (null === $string) {
            return null;
        }

        return (string)preg_replace('/[\x00-\x1F\x7F]/u', '', $string);
    }
}

if (!function_exists('number_to_roman_representation')) {
    /**
     * @param int $number
     * @return string
     */
    function number_to_roman_representation($number): string
    {
        $map = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
        $returnValue = '';
        while ($number > 0) {
            foreach ($map as $roman => $int) {
                if ($number >= $int) {
                    $number -= $int;
                    $returnValue .= $roman;
                    break;
                }
            }
        }
        return $returnValue;
    }
}

if (!function_exists('is_sequent_array')) {
    /**
     * @param array $array
     * @return bool
     */
    #[\JetBrains\PhpStorm\Pure]
    function is_sequent_array (array $array): bool
    {
        if ([] === $array) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }
}

if (!function_exists('is_assoc_array')) {
    /**
     * @param array $array
     * @return bool
     */
    #[\JetBrains\PhpStorm\Pure]
    function is_assoc_array (array $array): bool
    {
        if ([] === $array) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}

if (!function_exists('is_method_public')) {
    /**
     * @param $object
     * @param $method
     * @return ReflectionMethod|null
     * @throws Exception
     */
    function is_method_public($object, $method): ?ReflectionMethod
    {
        try {
            $reflection = new \ReflectionMethod($object, $method);

            if ($reflection->isPublic()) {
                return $reflection;
            }

            return null;
        } catch (\ReflectionException $e) {
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
}

if (!function_exists('get_method_protected')) {
    /**
     * @param $object
     * @param $method
     * @return ReflectionMethod|null
     * @throws Exception
     */
    function get_method_protected($object, $method): ?ReflectionMethod
    {
        try {
            $reflection = new \ReflectionMethod($object, $method);

            if ($reflection->isProtected()) {
                return $reflection;
            }

            return null;
        } catch (\ReflectionException $e) {
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
}

if (!function_exists('get_method')) {
    /**
     * @param $object
     * @param $method
     * @return ReflectionMethod|null
     * @throws Exception
     */
    function get_method($object, $method): ?ReflectionMethod
    {
        try {
            $reflection = new \ReflectionMethod($object, $method);

            if ($reflection->isPrivate()) {
                return $reflection;
            }

            return null;
        } catch (\ReflectionException $e) {
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
}

if (!function_exists('json_response')) {
    /**
     * @param bool $result
     * @param array $data
     * @param array $errors
     * @param int $status
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    function symfony_json_response(bool $result, $data = [], $errors = [], $status = 200): \Symfony\Component\HttpFoundation\JsonResponse
    {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
            'successful'   => $result,
            'responseData' => is_array($data) && isset($data['data']) && 1 === count($data) ? $data['data'] : $data,
            'errors'       => is_array($data) && isset($errors['errors']) && 1 === count($errors) ? $errors['errors'] : $errors,
        ], $status);
    }
}
