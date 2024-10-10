<?php

namespace SwooleBase\Foundation;

use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

final class MiddlewareHandler
{
    /** @var array */
    private static $alias = [];

    /** @var DI */
    private $di;

    /** @var array */
    private $cabinet;

    public function __construct(DI $di)
    {
        if (!$this->di) {
            $this->di = $di;
        }
    }

    /**
     * @param array $alias
     */
    public static function registerAlias(array $alias)
    {
        if (empty(self::$alias)) {
            self::$alias = $alias;
        }
    }

    /**
     * @param string|null $middleware_class
     * @return mixed
     */
    public function retrieve(string $middleware_class = null): mixed
    {
        if (null === $middleware_class) {
            return $this->cabinet;
        }

        return $this->cabinet[$middleware_class] ?? null;
    }

    /**
     * Gather the full class names for the middleware short-cut string.
     * Gather argument array if is injected
     *
     * @param string $name
     * @return array
     */
    public function gatherMiddlewareClassNameWithArguments(string $name): array
    {
        $name = trim($name);

        if (empty($name)) {
            return [null, null];
        }

        [$name, $parameters] = array_pad(explode(':', $name, 2), 2, null);
        return [self::$alias[$name] ?? $name, explode(',', $parameters ?? '')];
    }

    /**
     * @param $middleware
     * @param array|null $arguments
     * @throws ReflectionException
     */
    public function handle($middleware, array $arguments = null)
    {
        if (is_callable($middleware)) {
            $this->callbackHandler($middleware, $arguments);
        } elseif (is_string($middleware) && !isset($this->cabinet[$middleware])) {
            $this->objectHandler($middleware, $arguments);
        }
    }

    /**
     * @param $middleware
     * @param array|null $arguments
     * @throws ReflectionException
     */
    private function callbackHandler($middleware, array $arguments = null)
    {
        $ref = new ReflectionFunction($middleware);
        $number = $ref->getNumberOfParameters();

        if (0 === $number) {
            $ref->invoke();
        } else {
            $arguments = $this->getArgs($ref->getParameters(), $arguments ?? []);

            if ($number > count($arguments)) {
                throw new Exception(sprintf('Too few parameters passed to the function: %s:%d', $ref->getFileName(), $ref->getStartLine()));
            }

            $ref->invokeArgs($arguments);
        }
    }

    /**
     * @param $middleware
     * @param array|null $arguments
     * @throws ReflectionException
     */
    private function objectHandler($middleware, array $arguments = null)
    {
        if (class_exists($middleware)) {
            $obj = $this->di->make($middleware);

            if ($obj) {
                if (method_exists($obj, 'handle')) {
                    $ref = new ReflectionMethod($obj, 'handle');
                    $number = $ref->getNumberOfParameters();
                    if (0 === $number) {
                        $this->cabinet[$middleware] = $ref->invoke($obj);
                    } else {
                        $arguments = $this->getArgs($ref->getParameters(), $arguments ?? []);

                        if ($number > count($arguments)) {
                            throw new Exception(sprintf('Too few parameters passed to the function: %s::handle', get_class($obj)));
                        }

                        $this->cabinet[$middleware] = $ref->invokeArgs($obj, $arguments);
                    }
                }

                $this->cabinet[$middleware] = $obj;
            }
        }
    }

    /**
     * @param array $parameters
     * @param array $values
     * @return array
     * @throws ReflectionException
     */
    private function getArgs(array $parameters, array $values): array
    {
        $arguments = [];

        /** @var ReflectionParameter $parameter */
        foreach ($parameters as $parameter) {
            $type_name = (string)$parameter->getType();

            if (MiddlewareHandler::class === $type_name) {
                $arguments[] = $this;
            } elseif (DI::class === $type_name) {
                $arguments[] = $this->di;
            } elseif (class_exists($type_name) || interface_exists($type_name)) {
                $arguments[] = $this->di->make($type_name);
            } elseif (!empty($values)) {
                $arguments[] = array_shift($values);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $arguments[] = null;
            }
        }

        return $arguments;
    }
}
