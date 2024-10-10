<?php 

namespace SwooleBase\Foundation;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionType;
use SwooleBase\Foundation\Interfaces\IsStronglyClass;

/**
 * Dependency Injection
 * @package Source
 */
final class DI
{
    /** @var array */
    private static $bindings = [];

    /** @var mixed */
    private $factory;

    /** @var array */
    private $instances;

    public function __construct($instances = [])
    {
        $this->instances = $instances;
    }

    /**
     * Register an existing instance into the Dependency Injection
     * Returns true if the new registration is successful
     * 
     * @param string $abstract
     * @param object $object
     * @param bool $replace
     * @return bool
     */
    public function instance(string $abstract, object $object, bool $replace = false): bool
    {
        if (!isset($this->instances[$abstract]) || $replace) {
            $this->instances[$abstract] = $object;
            return (bool)$this->instances[$abstract];
        }

        return false;
    }

    /**
     * @param string $abstract
     * @param callable|string|null $concrete
     * @param bool $replace
     * @return mixed 
     */
    public function bind(string $abstract, callable|string $concrete = null, bool $replace = false): mixed
    {
        if ($concrete && ($replace || !isset(self::$bindings[$abstract]))) {
            self::$bindings[$abstract] = is_callable($concrete) ? call_user_func($concrete) : $concrete;
        }

        return self::$bindings[$abstract];
    }

    /**
     * @param mixed $factory
     */
    public function setFactory($factory): void
    {
        $this->factory = $factory;
    }

    /**
     * @param string $abstract
     * @param array $arguments
     * @return mixed
     * @throws Exception
     * @throws ReflectionException
     */
    public function makeArgs(string $abstract, array $arguments): mixed
    {        
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $resolved = $this->resolve($abstract, $arguments);

        if ($resolved instanceof IsStronglyClass) {
            $this->assertObject($resolved);
        }

        return $resolved;
    }

    /**
     * @param string $abstract
     * @return mixed
     * @throws Exception
     * @throws ReflectionException
     */
    public function make(string $abstract): mixed
    {
        if (DI::class === $abstract) {
            return $this;
        }

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $resolved = $this->resolve($abstract);

        if ($resolved instanceof IsStronglyClass) {
            $this->assertObject($resolved);
        }

        return $resolved;
    }

    /**
     * @param string $abstract
     * @param array|null $arguments
     * @return mixed
     * @throws ReflectionException|Exception
     */
    public function __invoke(string $abstract, array $arguments = null): mixed
    {
        return null === $arguments ? $this->make($abstract) : $this->makeArgs($abstract, $arguments);
    }

    /**
     * @param string $abstract
     * @param array|null $arguments
     * @return mixed
     * @throws ReflectionException
     * @throws Exception
     */
    private function resolve(string $abstract, array $arguments = null): mixed
    {
        $abstract = $this->getBinding($abstract);
        $reflector = new ReflectionClass($abstract);

        if (!$reflector->isInstantiable()) {
            throw new Exception("Class $abstract is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $abstract;
        }

        $parameters = $constructor->getParameters();

        $dependencies = $this->getDependencies($abstract, $parameters, $arguments);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * @param string $abstract
     * @return string
     */
    private function getBinding(string $abstract): string
    {
        if (isset($this->factory) && is_scalar($this->factory) && isset(self::$bindings[$abstract][$this->factory])) {
            $class = self::$bindings[$abstract][$this->factory];
        } elseif (isset(self::$bindings[$abstract])) {
            $class = self::$bindings[$abstract];
        }

        if (!isset($class) || (!is_string($class) && !is_object($class))) {
            return $abstract;
        }

        return $class;
    }

    /**
     * @param string $abstract
     * @param array $parameters
     * @param array|null $arguments
     * @return array
     * @throws Exception|ReflectionException
     */
    protected function getDependencies(string $abstract, array $parameters, array $arguments = null): array
    {
        $dependencies = [];

        /** @var ReflectionParameter $parameter */
        foreach ($parameters as $parameter) {
            if (isset($arguments[$parameter->getName()])) {
                $dependencies[] = $arguments[$parameter->getName()];
                continue;
            }

            $dependency = $parameter->getType();

            if ($dependency === null) {
                throw new Exception("Could not resolve dependency for parameter $parameter->name while instantiating $abstract");
            }

            /** @var ReflectionType $class_name */
            $class_name = trim("$dependency", '?');

            if (class_exists($class_name) || interface_exists($class_name)) {
                if ($arguments) {
                    $dependencies[] = $this->makeArgs($class_name, $arguments);
                } else {
                    $dependencies[] = $this->make($class_name);
                }
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } elseif ($dependency->allowsNull()) {
                $dependencies[] = null;
            }
        }

        return $dependencies;
    }

    /**
     * @param object $obj
     */
    private function assertObject(object $obj)
    {
        $asserts = $obj->assert();

        if (empty($asserts)) {
            return;
        }

        $ref = new ReflectionClass($obj);
        $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
        $error_list = [];

        foreach ($methods as $method) {
            $name = $method->getName();

            if ($method->isConstructor() || (isset($asserts[$name]) && false === $asserts[$name])) {
                continue;
            }

            $errors = [];

            if (!$method->hasReturnType()) {
                $errors[] = sprintf('The %s method must return a defined data type.', $name);
            }

            if ($method->getNumberOfParameters()) {
                foreach ($method->getParameters() as $parameter) {
                    $param = $parameter->getName();
                    if (!$parameter->hasType()) {
                        $errors[] = sprintf('The %s parameter of the %s method must have a defined data type', $param, $name);
                        continue;
                    }

                    $type = $parameter->getType();

                    if (isset($asserts[$name]) && is_string($asserts[$name]) && !is_subclass_of("$type", $asserts[$name])) {
                        $errors[] = sprintf('The %s parameter of the %s method must be an instance of %s.', $param, $name, $asserts[$name]);
                        continue;
                    }

                    if (isset($asserts[$name][$param]) && is_string($asserts[$name][$param]) && !is_subclass_of("$type", $asserts[$name][$param])) {
                        $errors[] = sprintf('The %s parameter of the %s method must be an instance of %s.', $param, $name, $asserts[$name][$param]);
                    }
                }
            }

            if ($errors) {
                $error_list = array_merge($error_list, $errors);
            }
        }

        if ($error_list) {
            $str = get_class($obj);
            throw new Exception("There is an error when checking the public methods of the class: $str.", ['key' => 'CLASS_INVALID', $str, $error_list]);
        }
    }
}
