<?php

namespace SwooleBase\Foundation\Http;

use Exception;
use ReflectionException;
use Swoole\Http\Request as SwooleHttpRequest;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class Request
 * @package SwooleBase\Foundation
 *
 * @method get(string $key, mixed $default = null)
 * @method string getBasePath()
 * @method string getBaseUrl()
 * @method string getRequestUri()
 * @method string getUri()
 * @method string getUriForPath()
 * @method string getMethod()
 */
class Request
{
    /** @var null|array */
    private $all = null;

    private $fd;

    /** @var HeaderBag */
    private $headers;

    /** @var SymfonyRequest */
    private $request;

    public function __construct($input, $fd = null)
    {
        if ($input instanceof SymfonyRequest) {
            $this->request = $input;
            $this->headers = $input->headers;
        }

        if (is_array($input)) {
            array_walk_recursive($input, function(&$v) {
                if (is_string($v)) {
                    $v = trim($v);
                }
            });

            $this->all = $input;
            $this->headers = new HeaderBag();
        }

        $this->fd = $fd;
    }

    /**
     * @param SwooleHttpRequest|null $request
     * @return array[SymfonyRequest, int]
     */
    public static function createRequest(SwooleHttpRequest $request = null): array
    {
        if (! $request) {
            SymfonyRequest::enableHttpMethodParameterOverride();
            return [SymfonyRequest::createFromGlobals(), null];
        }

        $r = SymfonyRequest::create(
            $request->server['request_uri'] ?? '/',
            $request->server['request_method'] ?? 'GET',
            $request->get ?? [], // Query params
            $request->cookie ?? [],
            $request->files ?? [],
            $request->server ?? [], // Server parameters
            $request->rawContent() // Content (raw body)
        );

        $post = $request->post; // if POST

        if (!empty($post)) {
            $r->request->add($post);
        }

        $r->headers = new ParameterBag($request->header ?? []);
        $r->query = new ParameterBag($request->get ?? []);

        return [$r, $request->fd];
    }

    /**
     * @return array|null
     */
    public function getAll(): ?array
    {
        if (!$this->all && $this->request instanceof SymfonyRequest) {
            $content_type = $this->request->headers->get('content-type', '');

            if ('application/json' === strtolower($content_type)) {
                $arr = json_decode($this->request->getContent(), true);
            } else {
                $arr = array_merge($this->request->query->all(), $this->request->request->all(), $this->request->files->all());
            }

            array_walk_recursive($arr, function(&$v) {
                if (is_string($v)) {
                    $v = trim($v);
                }
            });

            $this->all = $arr;
        }

        return $this->all;
    }

    /**
     * @return mixed
     */
    public function getFd(): mixed
    {
        return $this->fd;
    }

    /**
     * @param string $key
     * @param string|array|null $values
     * @param bool $replace
     */
    public function setHeaders(string $key, string|array|null $values, bool $replace = true): void
    {
        if ($replace || !$this->headers->has($key)) {
            $this->headers->set($key, $values);
        }
    }

    /**
     * @param string|null $key
     * @param string|null $default
     * @return array|string|null
     */
    public function getHeaders(?string $key = null, ?string $default = null): null|array|string
    {
        if (!$key) {
            return $this->headers->all();
        }

        return $this->headers->get($key, $default);
    }

    /**
     * @return SessionInterface
     */
    public function getSession(): SessionInterface
    {
        return $this->request->getSession();
    }

    /**
     * Kiểm tra xem có một phiên (session) trước đó đã được thiết lập hay chưa.
     *
     * @return bool
     */
    public function hasPreviousSession(): bool
    {
        return $this->request->hasPreviousSession();
    }

    /**
     * @param bool $skipIfUninitialized
     * @return bool
     */
    public function hasSession(bool $skipIfUninitialized = false): bool
    {
        return $this->request->hasSession($skipIfUninitialized);
    }

    /**
     * @return void
     */
    public function setSession(SessionInterface $session)
    {
        $this->request->setSession($session);
    }

    /**
     * @param callable(): SessionInterface $factory
     */
    public function setSessionFactory(callable $factory): void
    {
        $this->request->setSession($factory(...));
    }

    /**
     * @return string
     */
    public function getPathInfo(): string
    {
        $arr = explode('?', $this->request->getRequestUri(), 2);
        $str = preg_replace('/\/*$/', '', $arr[0]);
        return empty($str) ? '/' : $str;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws ReflectionException
     * @throws Exception
     */
    public function __call(string $name, array $arguments): mixed
    {
        $method = !($this->request instanceof SymfonyRequest) ? null : is_method_public($this->request, $name);

        if (!$method) {
            throw new Exception(SymfonyRequest::class. "::$name not exist or not is public");
            return null;
        }

        return $method->invokeArgs($this->request, $arguments);

    }

    public function __toString(): string
    {
        if ($this->request instanceof SymfonyRequest) {
            return (string)$this->request;
        }

        return !is_scalar($this->all) ? serialize($this->all) : (string)$this->all;
    }
}
