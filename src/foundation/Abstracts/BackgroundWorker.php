<?php

namespace SwooleBase\Foundation\Abstracts;

use ReflectionClass;
use SwooleBase\Foundation\DI;
use SwooleBase\Foundation\Interfaces\ExceptionHandler;
use Throwable;

abstract class BackgroundWorker
{
    /**
     * Parameters passed when running command in terminal.
     * Inherited classes get them using getArgument method.
     *
     * @var array
     */
    private $arguments;

    /**
     * The main method of the command.
     * This method is called when running a command in the terminal.
     *
     * @var string
     */
    private $method;

    /** @var null|string */
    protected static $storage_path = null;

    /**
     * BacklogWork constructor.
     * @param array $arguments
     * @param string $method
     */
    public final function __construct(array $arguments = [], $method = 'main')
    {
        $this->arguments = $arguments;
        $this->method = $method;
    }

    /**
     * @return array
     */
    public final function __serialize(): array
    {
        return ['arguments' => $this->arguments, 'method' => $this->method];
    }

    /**
     * @param array $data
     */
    public final function __unserialize(array $data): void
    {
        $this->arguments = $data['arguments'];
        $this->method = $data['method'];
    }

    /**
     * @return bool
     */
    public final function publish(): bool
    {
        if (!static::$storage_path) {
            return false;
        }

        $serialization_file = sprintf('/%s/%s.ser', trim(static::$storage_path, '/ '), date('Y-m-d_His'));
        return false !== file_put_contents($serialization_file, serialize($this));
    }

    /**
     * @param DI $di
     */
    public function middleware(DI $di): void
    {
    }

    /**
     * @param DI $di
     * @param string $lock_key
     * @return string
     */
    public function createLockOrThrow(DI $di, string $lock_key): string
    {
        return $lock_key;
    }

    /**
     * @param string $lock_key
     */
    public function unlock(string $lock_key)
    {
    }

    /**
     * @param DI $di
     */
    public final function call(DI $di)
    {
        $lock_key = str_replace('\\', '', get_called_class());
        $reflection = null;

        try {
            if (!$this->method) {
                throw new \Exception('The main method of the command is not set.');
            }

            $lock_key = $this->createLockOrThrow($di, $lock_key);
            $this->middleware($di);

            $reflection = new ReflectionClass($this);
            $method = $reflection->getMethod($this->method);

            if (0 === $method->getNumberOfParameters()) {
                $method->invoke($this);
                return;
            }

            $parameters = $method->getParameters();

            $arguments = [];

            foreach ($parameters as $parameter) {
                $type_name = (string)$parameter->getType();

                if (DI::class === $type_name) {
                    $arguments[] = $di;
                } elseif (class_exists($type_name) || interface_exists($type_name)) {
                    $arguments[] = $di->make($type_name);
                } else {
                    $arguments[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                }
            }

            $method->invokeArgs($this, $arguments);
        } catch (Throwable $exception) {
            /** @see ExceptionHandler */
            if ($exception instanceof ExceptionHandler) {
                $exception = $exception->handler();
            }

            if ($exception instanceof Throwable) {
                echo "\033[31m{$exception->getMessage()}\n{$exception->getFile()}: {$exception->getLine()}\033[0m\n\n{$exception->getTraceAsString()}";
            }
        } finally {
            $this->unlock($lock_key);
        }
    }

    /**
     * @param null $key
     * @return mixed
     */
    protected final function getArgument($key = null): mixed
    {
        if (null === $key) {
            return $this->arguments;
        }

        return $this->arguments[$key] ?? null;
    }
}
