<?php

namespace SwooleBase\Foundation\Http;

use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use SwooleBase\Foundation\DI;
use SwooleBase\Foundation\Interfaces\HasResponse;
use SwooleBase\Foundation\Interfaces\ResponseInterface;
use SwooleBase\Foundation\Interfaces\RouteHandlerInterface;
use SwooleBase\Foundation\Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

class RouteHandler implements RouteHandlerInterface
{
    /** @var null|array */
    private $param;

    /** @var string|callable */
    private $uses;

    /** @var array */
    private $middleware;

    /**
     * controller interface
     * @var string
     */
    protected static string $CI;

    /**
     * RouteHandler constructor.
     * @param array $route_found
     * @param string $request_class
     * @param object $request
     * @param string $response_class
     * @param object $response
     * @param DI $di
     */
    final public function __construct(array $route_found, private string $request_class, private object $request, private string $response_class, private object $response, private DI $di)
    {
        $this->parseIncomingRoute($route_found);
    }

    /**
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware && is_array($this->middleware) ? $this->middleware : [];
    }

    /**
     * @return mixed
     * @throws ReflectionException|Exception
     */
    public function handle(): mixed
    {
        if (!$this->uses) {
            return false;
        }

        if (is_callable($this->uses) && (($this->uses instanceof \Closure) || is_string($this->uses))) {

            $result = $this->callUserFunction();

            if (false !== $result) {
                return $this->parseResult($result);
            }

            return false;
        }

        [$controller, $method] = $this->uses;

        if (!empty(self::$CI) && !is_subclass_of($controller, self::$CI)) {
            throw new Exception('Controller invalid: '.$controller, ['key' => 'CONTROLLER_INVALID', $controller, self::$CI]);
        }

        if (method_exists($controller, '__invoke')) {
            $controller = $this->di->make($controller);
            return $this->parseResult($controller($method, $this, $this->param, $this->request, $this->response, $this->di));
        }

        $reflection_method = $this->prepareMethod($controller, $method);

        if (!$reflection_method) {
            throw new Exception("Method $controller::$method not exists.");
        }

        $controller = $this->di->make($controller);
        $result = $this->invokeMethod($reflection_method, $controller);

        if (false !== $result) {
            return $this->parseResult($result);
        }

        return false;
    }

    /**
     * @param array|ReflectionParameter[] $parameters
     * @return array
     * @throws ReflectionException|Exception
     */
    public function prepareArgs(array $parameters): array
    {
        $arguments = [];
        /** @var ReflectionParameter $parameter */
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if (isset($this->param[$name])) {
                $arguments[] = $this->param[$name];
            } else {
                $type_name = (string)$parameter->getType();

                if (DI::class === $type_name) {
                    $arguments[] = $this->di;
                } elseif ($type_name === $this->request_class) {
                    $arguments[] = $this->request;
                } elseif ($type_name === $this->response_class) {
                    $arguments[] = $this->response;
                } elseif (class_exists($type_name) || interface_exists($type_name)) {
                    $arguments[] = $this->di->make($type_name);
                } else {
                    $arguments[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                }
            }
        }

        return $arguments;
    }

    /**
     * @param $result
     * @return mixed
     */
    private function parseResult($result)
    {
        if ($this->response instanceof ResponseInterface) {
            if ($result instanceof JsonResponse) {
                $this->response->merge($result);
                return $this->response;
            }

            if ($result instanceof BinaryFileResponse) {
                $this->response->merge($result);
                return $this->response;
            }

            if ($result instanceof HasResponse) {
                $result->respond($this->response);

                return $this->response;
            }

            if (is_string($result)) {
                $this->response->setContent($result);
                return $this->response;
            }
        }

        return $result;
    }

    /**
     * @return mixed
     * @throws ReflectionException|Exception
     */
    private function callUserFunction(): mixed
    {
        $reflection_function = new ReflectionFunction($this->uses);

        if (0 === $reflection_function->getNumberOfParameters()) {
            return $reflection_function->invoke();
        }

        $parameters = $reflection_function->getParameters();

        return $reflection_function->invokeArgs($this->prepareArgs($parameters));
    }

    /**
     * @param $class
     * @param $method
     * @return ReflectionMethod|null
     * @throws ReflectionException
     */
    private function prepareMethod($class, $method): ?ReflectionMethod
    {
        if (!method_exists($class, $method)) {
            return null;
        }

        $reflection_method = new ReflectionMethod($class, $method);

        if (!$reflection_method->isPublic()) {
            return null;
        }

        return $reflection_method;
    }

    /**
     * @param ReflectionMethod $reflection_method
     * @param object $controller
     * @return mixed
     * @throws ReflectionException|Exception
     */
    private function invokeMethod(ReflectionMethod $reflection_method, object $controller): mixed
    {
        if (0 === $reflection_method->getNumberOfParameters()) {
            return $reflection_method->invoke($controller);
        }

        $parameters = $reflection_method->getParameters();

        return $reflection_method->invokeArgs($controller, $this->prepareArgs($parameters));
    }

    /**
     * @param array $route_found
     */
    private function parseIncomingRoute(array $route_found)
    {
        $this->param = $route_found[1] ?? null;
        $action = !isset($route_found[0]) || !is_array($route_found[0]) ? [] : $route_found[0];
        $this->middleware = isset($action['middleware']) ? (array) $action['middleware'] : [];
        $this->uses = $action['uses'];
    }

}
