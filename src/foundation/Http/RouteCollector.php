<?php

namespace SwooleBase\Foundation\Http;

use FastRoute\DataGenerator;
use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteParser;
use FastRoute\RouteParser\Std;
use SwooleBase\Foundation\Exception;

/**
 * Class RouteCollector
 * @package SwooleBase\Foundation\Http
 * @see \FastRoute\RouteCollector
 *
 * @property string $middleware
 * @property string $prefix
 * @property string $as
 *
 * @method void get(string $uri, array|null $option = null, array|callable $handler = [])
 * @method void post(string $uri, array|null $option = null, array|callable $handler = [])
 * @method void put(string $uri, array|null $option = null, array|callable $handler = [])
 * @method void delete(string $uri, array|null $option = null, array|callable $handler = [])
 * @method void patch(string $uri, array|null $option = null, array|callable $handler = [])
 * @method void head(string $uri, array|null $option = null, array|callable $handler = [])
 * @method void trace(string $uri, array|null $option = null, array|callable $handler = [])
 * @method void connect(string $uri, array|null $option = null, array|callable $handler = [])
 * @method void options(string $uri, array|null $option = null, array|callable $handler = [])
 */
final class RouteCollector
{
    public const ANY = '/{any:.*}';

    /** @var DataGenerator|GroupCountBased|null */
    private static $data_generator = null;
    private static $data_generator_get = null;
    private static $data_generator_post = null;
    /** @var RouteParser|Std */
    private static $route_parser;
    /** @var array */
    private static $routes_registered = [];

    /** @var string[] */
    private array $group_attribute_name = ['prefix', 'as', 'middleware'];
    /** @var string[] */
    private array $method_magic = ['get', 'post', 'put', 'delete', 'patch', 'head', 'trace', 'connect', 'options'];
    /** @var string */
    private string $middleware = '';
    private string $as = '';
    private string $prefix;
    /** @var array The route group attribute stack. */
    private array $stack = [];

    /**
     * @param string|null $method
     * @param string|null $uri
     * @param null $action
     * @param string $as
     * @throws \Exception
     */
    private static function register(string $method = null, string $uri = null, $action = null, string $as = '')
    {
        if (isset(self::$routes_registered[$method.$uri])) {
            throw new \Exception('This route cannot be created because it is already registered: '.$method.$uri);
        }

        if (isset($action['as'])) {
            $as .= $action['as'];
            unset($action['as']);
        }

        self::dataGenerator($method, $uri, $action);
        self::$routes_registered[$method.$uri] = $as;
    }

    /**
     * Adds a route to the collection.
     *
     * @param string $http_method
     * @param string $route
     * @param $action
     */
    private static function dataGenerator(string $http_method, string $route, $action)
    {
        $arr_route_data = self::$route_parser->parse($route);

        if (preg_match('/^(get|delete)$/i', $http_method)) {
            foreach ($arr_route_data as $route_data) {
                self::$data_generator_get->addRoute($http_method, $route_data, $action);
            }
        } elseif (preg_match('/^(post|put)$/i', $http_method)) {
            foreach ($arr_route_data as $route_data) {
                self::$data_generator_post->addRoute($http_method, $route_data, $action);
            }
        } else {
            foreach ($arr_route_data as $route_data) {
                self::$data_generator->addRoute($http_method, $route_data, $action);
            }
        }
    }

    /**
     * RouteCollector constructor.
     * @param string|null $prefix
     */
    public final function __construct(string $prefix = null)
    {
        $this->prefix = empty($prefix) ? '' : sprintf('/%s', strtolower(trim($prefix, '/ ')));

        if (!self::$data_generator) {
            self::$data_generator = new GroupCountBased();
            self::$data_generator_get = new GroupCountBased();
            self::$data_generator_post = new GroupCountBased();
            self::$route_parser = new Std();
        }
    }

    public function __set(string $name, $value): void
    {
        if ('prefix' === $name) {
            $this->prefix = empty($value) ? '' : sprintf('/%s', strtolower(trim($value, '/ ')));
        }

        if ('middleware' === $name && !$this->middleware) {
            $this->middleware = $value;
        }

        if ('as' === $name && !$this->middleware) {
            $this->as = $value;
        }
    }

    /**
     * Register a set of routes with a set of shared attributes.
     *
     * @param array $attributes
     * @param \Closure $callback
     */
    public function group(array $attributes, \Closure $callback)
    {
        // attributes filter
        $attributes = array_intersect_key($attributes, array_flip(['prefix', 'as', 'middleware']));

        if (isset($attributes['middleware']) && is_string($attributes['middleware'])) {
            $attributes['middleware'] = explode('|', $attributes['middleware']);
        }

        $this->updateGroupStack($attributes);

        $callback($this);

        array_pop($this->stack);
    }

    /**
     * Update the group stack with the given attributes.
     *
     * @param array $attributes
     */
    private function updateGroupStack(array $attributes)
    {
        if (isset($attributes['prefix'])) {
            $attributes['prefix'] = preg_replace('/(\/+)/', '/', trim($attributes['prefix'], ' /'));
        }

        if (isset($attributes['as'])) {
            $attributes['as'] = preg_replace('/(\.+)/', '.', trim($attributes['as'], ' .'));
        }

        if (! empty($this->stack)) {
            $attributes = $this->mergeGroup($attributes, end($this->stack));
        }

        $this->stack[] = $attributes;
    }

    /**
     * Add a route to the collection.
     *
     * @param string $method
     * @param string $uri
     * @param array|null $option
     * @param array|callable $handler
     * @throws \Exception
     */
    public function addRoute(string $method, string $uri, ?array $option, array|callable $handler)
    {
        $action = $this->parseAction($option ?? [], $handler, $uri);
        $uri = preg_replace('/\/\{.*\:?\.?\*\}/', self::ANY, $uri);
        $attributes = null;

        if (! empty($this->stack)) {
            $attributes = $this->mergeGroup([], end($this->stack));
        }

        if (isset($attributes) && is_array($attributes)) {
            if (isset($attributes['prefix'])) {
                $uri = trim($attributes['prefix'], '/')
                    .'/'.trim($uri, '/');
            }

            $action = $this->mergeGroupAttributes($action, $attributes);
        }

        $uri = $this->prefix. '/'.trim($uri, '/');

        if (is_array($action)) {
            $action = $this->mergeMiddleware($action);
        }

        $as = trim($this->as, '.');
        self::register($method, $uri, $action, $as ? "$as." : '');

    }

    /**
     * @param string $name
     * @param array $args
     * @throws \Exception
     */
    public function __call(string $name, array $args)
    {
        if (in_array($name, $this->method_magic, true) && 3 === count($args)) {
            $this->addRoute(strtoupper($name), $args[0], $args[1], $args[2]);
        } elseif (in_array($name, $this->method_magic, true) && 2 === count($args)) {
            $this->addRoute(strtoupper($name), $args[0], [], $args[1]);
        } else {
            throw new \Exception('Method not allow');
        }
    }

    /**
     * Merge the given group attributes.
     *
     * @param array $new
     * @param array $old
     * @return array
     */
    private function mergeGroup(array $new, array $old) :array
    {
        $new['prefix'] = !isset($new['prefix']) ? ($old['prefix'] ?? '') : sprintf('%s/%s', $old['prefix'], $new['prefix']);
        // merge alias
        $new['as'] = trim(sprintf('%s.%s', $old['as'] ?? '', $new['as'] ?? ''), '.');
        // filter
        $arr_except = array_diff_key($old, array_flip(['prefix', 'as']));

        return array_merge_recursive($arr_except, $new);
    }

    /**
     * @param $option
     * @param $handler
     * @param string $uri
     * @return array
     * @throws Exception
     */
    private function parseAction($option, $handler, string $uri): array
    {
        if (is_array($handler) && 1 === count($handler) && is_callable($handler[0])) {
            $handler = $handler[0];
        }

        if (!is_callable($handler)) {
            if (!isset($handler[0]) || !is_string($handler[0]) || !class_exists($handler[0])) {
                throw new Exception('Invalid route handler method. The method must be callable or a class name. URI: '.$uri);
            }
        }

        if (isset($option['middleware']) && is_string($option['middleware'])) {
            $option['middleware'] = explode('|', $option['middleware']);
        }

        return array_merge(array_intersect_key($option, ['middleware' => '', 'as' => '']), ['uses' => $handler]);
    }

    /**
     * Merge the group attributes into the action.
     *
     * @param  array  $action
     * @param  array  $attributes
     * @return array
     */
    private function mergeGroupAttributes(array $action, array $attributes): array
    {
        $middleware = $attributes['middleware'] ?? null;
        $as = $attributes['as'] ?? null;
        return $this->mergeMiddlewareGroup($this->mergeAsGroup($action, $as), $middleware);
    }

    /**
     * Merge the middleware group into the action.
     *
     * @param array $action
     * @param array|null $middleware
     * @return array
     */
    private function mergeMiddlewareGroup(array $action, array $middleware = null): array
    {
        if ($middleware) {
            if (isset($action['middleware'])) {
                if (!is_array($action['middleware'])) {
                    $action['middleware'] = [$action['middleware']];
                }

                $action['middleware'] = array_merge($middleware, $action['middleware']);
            } else {
                $action['middleware'] = $middleware;
            }
        }

        return $action;
    }

    /**
     * Merge the as group into the action.
     *
     * @param array $action
     * @param string|null $as
     * @return array
     */
    private function mergeAsGroup(array $action, string $as = null): array
    {
        if (isset($as) && ! empty($as)) {
            if (isset($action['as'])) {
                $action['as'] = $as.'.'.$action['as'];
            } else {
                $action['as'] = $as;
            }
        }

        return $action;
    }

    /**
     * @param array $action
     * @return array
     */
    private function mergeMiddleware(array $action): array
    {
        if ($this->middleware) {
            $middleware = explode('|', $this->middleware);
            $action['middleware'] = array_unique(array_merge($middleware, $action['middleware'] ?? []));
        }

        if (isset($action['middleware']) && empty($action['middleware'])) {
            unset($action['middleware']);
        }

        return $action;
    }
}
