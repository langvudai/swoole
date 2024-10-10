<?php

namespace SwooleBase\Foundation;

use Closure;
use FastRoute\DataGenerator;
use FastRoute\Dispatcher as FastRouteDispatcherInterface;
use FastRoute\Dispatcher\GroupCountBased as FastRouteDispatcher;
use ReflectionClass;
use ReflectionException;
use SwooleBase\Foundation\Abstracts\BackgroundWorker;
use SwooleBase\Foundation\Http\Request;
use SwooleBase\Foundation\Interfaces\ResponseInterface;
use SwooleBase\Foundation\Interfaces\RouteHandlerInterface;
use SwooleBase\Foundation\Interfaces\Storable;
use SwooleBase\Foundation\Http\Response;
use SwooleBase\Foundation\Http\RouteCollector;
use SwooleBase\Foundation\Interfaces\ExceptionHandler;
use SwooleBase\Foundation\Interfaces\Router;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * @param mixed ...$arguments
 * @return mixed|Console
 */
function console(...$arguments): mixed
{
    if (empty($arguments)) {
        return Console::instance();
    }

    return Console::instance()->accessible($arguments);
}

/**
 * Class Console
 * @package SwooleBase\Foundation
 *
 * @property $worker_num
 * @property $task_worker_num
 * @property $daemonize
 * @property $max_request
 * @property $log_file
 * @property $pid_file
 * @property $backlog
 * @property $heartbeat_check_interval
 * @property $heartbeat_idle_time
 * @property $dispatch_mode
 * @property $open_http_protocol
 * @property $open_websocket_protocol
 * @property $open_tcp_nodelay
 * @property $enable_static_handler
 * @property $document_root
 * @property $max_connection
 * @property $buffer_output_size
 * @property $socket_buffer_size
 * @property $task_ipc_mode
 * @property $task_max_request
 * @property $task_timeout
 * @property $ssl_cert_file
 * @property $ssl_key_file
 *
 * @method onStart(callable $callback)
 * @method onShutdown(callable $callback)
 * @method onWorkerStart(callable $callback)
 * @method onWorkerStop(callable $callback)
 * @method onMessage(callable $callback)
 * @method onTask(callable $callback)
 * @method onFinish(callable $callback)
 * @method onPipeMessage(callable $callback)
 * @method onWorkerError(callable $callback)
 * @method onClose(callable $callback)
 */
class Console 
{
    /** @var Console */
    private static $singleton;

    /** @var string */
    private $application_namespace;

    /** @var string */
    private $application_path;

    /** @var string */
    private $base_path;

    /** @var FastRouteDispatcherInterface */
    private $dispatcher;

    private $dispatcher_get;

    private $dispatcher_post;

    /** @var string */
    private $exception_handler;

    /** @var Closure|null */
    private $ajax_handle;

    /** @var array */
    private $middlewares;

    /** @var Storable[] */
    private $stores;

    private $server;

    /** @var array */
    private $server_settings;

    private $server_events;

    /** @var callable */
    private $server_before_start;

    private $response_factory;

    private $request_factory;

    /** @var string */
    protected string $handle_route = 'SwooleBase\Foundation\Http\RouteHandler';

    /**
     * @return Console
     */
    final public static function instance(): Console
    {
        return self::$singleton;
    }

    /**
     * Dispatcher constructor.
     * @param string $base_path
     * @param string $application_namespace
     * @param string $application_path
     * @throws \Exception
     */
    final public function __construct(string $base_path, $application_namespace = '', $application_path = '')
    {
        if (self::$singleton) {
            throw new \Exception('construct failed');
        }

        if (!$base_path) {
            $base_path = ('cli' === php_sapi_name() || 'phpdbg' == php_sapi_name()) ? getcwd() : realpath(getcwd().'/../');
        } else {
            $base_path = realpath($base_path);
        }

        $this->base_path = $base_path ? $base_path : '';

        $this->application_namespace = empty($application_namespace) ? '' : ucfirst(trim($application_namespace, '\\ ') .'\\');

        if (!$application_path) {
            $this->application_path = $this->base_path .'/'. lcfirst(trim(str_replace('\\', '/', $this->application_namespace), '/ '));
        } else {
            $this->application_path = $application_path;
        }

        $this->stores = [];
        $this->exception_handler = null;

        $this->response_factory = fn() => $this->createResponse();
        $this->request_factory = fn ($http_request) => $this->createRequest($http_request);

        if (!self::$singleton) {
            self::$singleton = $this;
        }
    }

    /**
     * @param callable $callback
     * @return mixed
     * @throws Exception
     */
    final public function __invoke(callable $callback): mixed
    {
        if (!$this->server) {
            throw new Exception('Not swoole server to control.');
        }

        return $callback($this->server);
    }

    public function __set(string $name, $value): void
    {
        if (!in_array($name, ['worker_num', 'task_worker_num', 'daemonize', 'max_request', 'log_file', 'pid_file', 'backlog',
            'heartbeat_check_interval', 'heartbeat_idle_time', 'dispatch_mode', 'open_http_protocol', 'open_websocket_protocol',
            'open_tcp_nodelay', 'enable_static_handler', 'document_root', 'max_connection', 'buffer_output_size', 'socket_buffer_size',
            'task_ipc_mode', 'task_max_request', 'task_timeout', 'ssl_cert_file', 'ssl_key_file'])) {
            throw new Exception('swoole server does not have this setting: '.$name.'.');
        }

        $this->server_settings[$name] = $value;
    }

    /**
     * @param array $settings
     * @return array
     */
    public function serverSettings(array $settings): array
    {
        if (!is_array($this->server_settings) || empty($this->server_settings)) {
            return $settings;
        }

        return array_merge($settings, $this->server_settings);
    }

    public function __call(string $name, array $arguments)
    {
        if (!preg_match('/^on([A-Z][a-zA-Z0-9_]+)$/', $name, $matches)) {
            throw new Exception("$name is not a swoole server event call.");
        }

        $name = lcfirst($matches[1]);

        if (!in_array($name, ['start', 'shutdown', 'workerStart', 'workerStop', 'message', 'task', 'finish', 'pipeMessage', 'workerError', 'close'])) {
            throw new Exception("$name is not a swoole server event.");
        }

        $callback = array_shift($arguments);

        if (!is_callable($callback)) {
            throw new Exception('The '.$name.' event must have a callback set up.');
        }

        $this->server_events[$name] = $callback;
    }

    /**
     * @param callable $callback
     */
    public function beforeStart(callable $callback)
    {
        $this->server_before_start = $callback;
    }

    /**
     * @param $server
     * @throws \Exception
     */
    public function onBeforeStart($server)
    {
        try {
            if (!$this->server) {
                throw new Exception('Not swoole server to control.');
            }

            if (is_array($this->server_events)) {
                foreach ($this->server_events as $event => $callback) {
                    $server->on($event, $callback);
                }
            }

            if (is_callable($this->server_before_start)) {
                call_user_func_array($this->server_before_start, [$this]);
            }
        } catch (Throwable $exception) {
            $this->exceptionHandleOrExit($exception);
        }
    }

    /**
     * @param $server
     * @return void
     */
    final public function swooleServer($server)
    {
        if (!$this->server) {
            $this->server = $server;
        }
    }

    /**
     * register an Foundation\Interfaces\Middleware interface array or a callback
     *
     * @param $middlewares
     */
    final public function middlewares($middlewares)
    {
        if (!is_array($middlewares)) {
            $this->middlewares = [$middlewares];
        } else {
            $this->middlewares = $middlewares;
        }
    }

    /**
     * @param string $class
     * @return $this
     * @throws \Exception
     */
    final public function loadRoute(string $class): Console
    {
        try {
            if (class_exists($class) && is_subclass_of($class, Router::class)) {
                $name = preg_replace('/^.*\\\\/', '', $class);
                /*  convert to hyphen  */
                $name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));
                $router = new $class(new RouteCollector($name));
                unset($router);
            }

        } catch (Throwable $exception) {
            $this->exceptionHandleOrExit($exception);
        }

        return $this;
    }

    /**
     * @param object|null $argument
     * @return object|null
     */
    final public function ajaxHandle(object $argument = null): ?object
    {
        if (is_callable($argument) && $argument instanceof Closure) {
            $this->ajax_handle = $argument;
            return $argument;
        }
        if ($argument && is_callable($this->ajax_handle) && $this->ajax_handle instanceof Closure) {
            $call = $this->ajax_handle;
            return $call($argument);
        }

        return null;
    }

    /**
     * @param string $class_name
     */
    final public function exceptionHandler(string $class_name)
    {
        if (class_exists($class_name)) {
            $interfaces = class_implements($class_name);

            if (in_array(ExceptionHandler::class, $interfaces)) {
                $this->exception_handler = $class_name;
            }
        }
    }

    /**
     * @return string
     */
    final public function getBasePath(): string
    {
        return $this->base_path;
    }

    /**
     * @return string
     */
    final public function getApplicationPath(): string
    {
        return $this->application_path;
    }

    /**
     * @return string
     */
    final public function getApplicationNamespace(): string
    {
        return $this->application_namespace;
    }

    /**
     * @param string|null $path
     * @return string
     */
    public function basePath(?string $path = ''): string
    {
        return rtrim(sprintf('%s/%s', $this->getBasePath(), trim($path, '/ ')), '/');
    }

    /**
     * @param string|null $path
     * @return string
     */
    public function baseApplicationPath(?string $path = ''): string
    {
        return rtrim(sprintf('%s/%s', $this->getApplicationPath(), trim($path, '/ ')), '/');
    }

    /**
     * @param string $class
     * @return string
     */
    public function className(string $class): string
    {
        $class_name = sprintf('%s%s', $this->getApplicationNamespace(), trim($class, '\\ '));

        if (class_exists($class_name)) {
            return $class_name;
        }

        return $class_name. '\\';
    }

    /**
     * @param array $alias
     */
    final public function classAlias(array $alias)
    {
        array_walk($alias, 'class_alias');
    }

    /**
     * @param array $arguments
     * @return mixed
     * @throws \Exception
     */
    final public function accessible(array $arguments): mixed
    {
        try {
            $first = array_shift($arguments);

            if (!$first) {
                throw new Exception('too few arguments.');
            }

            if (!is_string($first)) {
                return $this->privateAccess((object)$first, $arguments);
            }

            if (class_exists($first) && is_subclass_of($first, Storable::class)) {
                $store = array_shift($arguments);
                if (is_object($store) && get_class($store) === $first) {
                    return (bool)($this->stores[$first] = $store);
                }

                return $this->stores[$first] ?? null;
            }

            return $this->staticAccess($first, $arguments);
        } catch (Throwable $exception) {
            $this->exceptionHandleOrExit($exception);
        }

        return null;
    }

    /**
     * @param $serialized_file
     * @throws \Exception
     */
    final public function terminal($serialized_file)
    {
        try {
            if (!file_exists($serialized_file)) {
                throw new Exception("$serialized_file not exists.");
            }

            if (!is_file($serialized_file)) {
                throw new Exception("$serialized_file not is file.");
            }

            if (!is_readable($serialized_file)) {
                throw new Exception("$serialized_file cannot read.");
            }

            $di = new DI();

            $serialized_data = file_get_contents($serialized_file);
            $result = unserialize($serialized_data);

            if (false === $result && $serialized_data !== serialize(false)) {
                // Check also the case serialized to 'b:0;'
                throw new Exception('Unable to deserialize data. File: '.$serialized_file);
            }

            if ($result instanceof BackgroundWorker) {
                $result->call($di);
            }

        } catch (Throwable $exception) {
            $this->exceptionHandleOrExit($exception);
        }
    }

    /**
     * @return array
     */
    public function createResponse(): array
    {
        $response = new Response();
        $response->setHeader('micro-time', microtime());

        return [Response::class, $response];
    }

    /**
     * @param $http_request
     * @return array
     */
    public function createRequest($http_request): array
    {
        [$symfony_request, $fd] = Request::createRequest($http_request);
        return [Request::class, new Request($symfony_request, $fd)];
    }

    /**
     * @param $http_request
     * @return mixed
     * @throws \Exception
     */
    final public function dispatch($http_request): mixed
    {
        $result = null;
        [$response_class, $response] = call_user_func($this->response_factory);

        try {
            $this->createDispatcher();
            [$request_class, $request] = call_user_func_array($this->request_factory, [$http_request]);
            $di = new DI([$response_class => $response, $request_class => $request]);
            $middleware_handler = new MiddlewareHandler($di);
            $di->instance(MiddlewareHandler::class, $middleware_handler);

            if ($this->middlewares && is_array($this->middlewares)) {
                foreach ($this->middlewares as $middleware) {
                    $middleware_handler->handle($middleware);
                }
            }

            $method = $request->getMethod();
            $request_uri = $request->getPathInfo();
            $result = $this->findRoute($method, $request_uri);

            if ($result && class_exists($this->handle_route) && is_subclass_of($this->handle_route, RouteHandlerInterface::class)) {
                $route_handler = new $this->handle_route($result, $request_class, $request, $response_class, $response, $di);

                foreach ($route_handler->getMiddleware() as $middleware) {
                    [$class_name, $arguments] = $middleware_handler->gatherMiddlewareClassNameWithArguments($middleware);
                    $middleware_handler->handle($class_name, $arguments);
                }

                $result = $route_handler->handle();

                if (false === $result && $response instanceof ResponseInterface) {
                    $response->setStatusCode(ResponseInterface::SERVICE_UNAVAILABLE); // 503
                    $response->setHeader('Retry-After', 3600);
                    $response->setContent('handle fail');
                    $result = $response;
                }

                unset($route_handler);
            }

            unset($middleware_handler);
            unset($request);
            unset($di);
        } catch (Throwable $exception) {
            $result = $this->exceptionHandleOrExit($exception);
        }

        return $result;
    }

    /**
     * @throws \Exception
     */
    public function run()
    {
        $response = $this->dispatch(null);

        if ($response instanceof SymfonyResponse) {
            $response->send();
        }

        if (is_scalar($response)) {
            echo $response;
        }
    }

    /**
     * @throws Exception|\Exception
     */
    private function createDispatcher()
    {
        if ($this->dispatcher instanceof FastRouteDispatcherInterface ||
            $this->dispatcher_get instanceof FastRouteDispatcherInterface ||
            $this->dispatcher_post instanceof FastRouteDispatcherInterface
        ) {
            return;
        }

        $routes_registered = $this->accessible([RouteCollector::class, '$routes_registered']);

        /*
         * action for OPTIONS method with any uri
         */
        if ($this->ajax_handle) {
            if (!isset($routes_registered['OPTIONS'.RouteCollector::ANY])) {
                $this->accessible([RouteCollector::class, 'dataGenerator', 'OPTIONS', RouteCollector::ANY, ['uses' => $this->ajax_handle]]);
            }
        }

        if (empty($this->accessible([RouteCollector::class, '$routes_registered']))) {
            throw new Exception('No route has been determined yet!');
        }

        $data_generator = $this->accessible([RouteCollector::class, '$data_generator']);
        $dg_get = $this->accessible([RouteCollector::class, '$data_generator_get']);
        $dg_post = $this->accessible([RouteCollector::class, '$data_generator_post']);

        if (!($data_generator instanceof DataGenerator) && !($dg_get instanceof DataGenerator) && !($dg_post instanceof DataGenerator)) {
            throw new Exception('Failed to generate route data');
        }

        if ($data_generator) {
            $this->dispatcher = new FastRouteDispatcher($data_generator->getData());
        }

        if ($dg_get) {
            $this->dispatcher_get = new FastRouteDispatcher($dg_get->getData());
        }

        if ($dg_post) {
            $this->dispatcher_post = new FastRouteDispatcher($dg_post->getData());
        }
    }

    /**
     * Handle the response from the FastRoute dispatcher.
     *
     * @param string $method
     * @param string $uri
     * @return array
     * @throws Exception
     */
    private function findRoute(string $method, string $uri): array
    {
        $result = match (strtoupper($method)) {
            'GET','DELETE' => $this->dispatcher_get->dispatch($method, $uri),
            'POST','PUT' => $this->dispatcher_post->dispatch($method, $uri),
            default => $this->dispatcher->dispatch($method, $uri)
        };
        
        $dispatcher = array_shift($result);

        if (FastRouteDispatcherInterface::FOUND !== $dispatcher) {
            if (FastRouteDispatcherInterface::NOT_FOUND === $dispatcher) {
                throw new Exception('Not Found', ['status_code' => 404, sprintf('The URI "%s" was not found', $uri)]);
            }

            if (FastRouteDispatcherInterface::METHOD_NOT_ALLOWED === $dispatcher) {
                if (1 === count($result) && 1 === count($result[0]) && 'OPTIONS' === $result[0][0]) {
                    throw new Exception('Route not found', ['status_code' => 404]);
                }

                throw new Exception('Method Not Allowed', ['status_code' => 405, sprintf('Method "%s" is not in list [%s]', $method, implode(', ', $result))]);
            }

            throw new Exception('Internal Server Error', ['status_code' => 500, '#fast-route-dispatcher', $method, $uri, date('Y-m-d H:i')]);
        }

        return $result;
    }

    /**
     * @param object $obj
     * @param array $arguments
     * @return mixed
     * @throws Exception
     * @throws ReflectionException
     */
    private function privateAccess(object $obj, array $arguments): mixed
    {
        $name = array_shift($arguments);

        if (!is_string($name)) {
            throw new Exception('Property name or method name must be string');
        }

        if (str_starts_with($name, '$') && property_exists($obj, $name = substr($name, 1))) {
            $property = (new ReflectionClass($obj))->getProperty($name);

            if ($arguments) {
                $property->setValue($obj, array_shift($arguments));
                return true;
            }

            return $property->getValue($obj);
        }

        if (method_exists($obj, $name)) {
            $reflection_class = new ReflectionClass($obj);
            $method = $reflection_class->getMethod($name);

            if ($arguments) {
                return $method->invokeArgs($obj, $arguments);
            }

            return $method->invoke($obj);
        }

        return null;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws ReflectionException
     * @throws Exception
     */
    private function staticAccess(string $name, array $arguments): mixed
    {
        if (!class_exists((string)$name)) {
            throw new Exception("Class '$name' not exists");
        }

        $static = array_shift($arguments);
        $reflection_class = new ReflectionClass($name);

        if (!$static) {
            throw new Exception(sprintf('Error: %s::%s is not a static property or static method', $name, (string)$static));
        }

        if (str_starts_with($static, '$')) {
            $property = $reflection_class->getProperty(substr($static, 1));

            if (!$property->isStatic()) {
                throw new Exception("Cannot access non-static property $name::$static");
            }

            return !$arguments ? $property->getValue() : ($property->setValue(array_shift($arguments)) ?? null);
        }

        $method = $reflection_class->getMethod($static);

        if (!$method->isStatic()) {
            throw new Exception("Cannot access non-static method $name::$static");
        }

        if (0 === $method->getNumberOfParameters()) {
            return $method->invoke(null);
        }

        return !$arguments ? function(...$args) use ($method) {
            return $method->invokeArgs(null, $args);
        } : $method->invokeArgs(null, $arguments);
    }

    /**
     * @param Throwable $exception
     * @return mixed
     * @throws \Exception
     */
    private function exceptionHandleOrExit(Throwable $exception): mixed
    {
        $exception = $this->getException($exception);

        if ($exception instanceof Throwable) {
            $errors = ($exception instanceof  Exception) ? json_encode($exception->getErrors()) : null;

            if (defined('SWOOLE_VERSION')) {
                /** @var ResponseInterface $response */
                [,$response] = $this->createResponse();
                $response->setHeader('cache-control', ['no-cache, private']);
                $response->setHeader('date', [(new \DateTime('now', new \DateTimeZone('GMT')))->format('D, d M Y H:i:s \G\M\T')]);
                $response->setContent("<title>SWOOLE Error {$exception->getCode()}</title>" .
                    "<h3 style='color: red'>{$exception->getMessage()}</h3>" .
                    "<pre>{$exception->getFile()}:{$exception->getLine()}\n{$exception->getTraceAsString()}</pre>" .
                    "<div>{$errors}</div>");

                return $response;
            }

            if ('cli' === php_sapi_name() || 'phpdbg' === php_sapi_name()) {
                echo "\033[31m{$exception->getMessage()}\n{$exception->getFile()}: {$exception->getLine()}\033[0m\n\n{$exception->getTraceAsString()}";
                exit();
            }

            echo "<title>Error {$exception->getCode()}</title><h3 style='color: red'>{$exception->getMessage()}</h3>" .
                "<pre>{$exception->getFile()}:{$exception->getLine()}\n{$exception->getTraceAsString()}</pre>" .
                "<div>{$errors}</div>";
            exit();
        }

        return $exception;
    }

    /**
     * @param Throwable $exception
     * @return mixed
     */
    private function getException(Throwable $exception): mixed
    {
        if ($this->exception_handler) {
            return (new $this->exception_handler($exception))->handler();
        }

        /** @see ExceptionHandler */
        if ($exception instanceof ExceptionHandler) {
            return $exception->handler();
        }

        return $exception;
    }
}