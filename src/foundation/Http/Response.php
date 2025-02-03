<?php

namespace SwooleBase\Foundation\Http;

use SwooleBase\Foundation\Interfaces\ResponseInterface;
use SwooleBase\Foundation\Exception;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class Response extends SymfonyResponse implements ResponseInterface
{
    /** @var null|Throwable */
    private $exception = null;

    /**
     * Response constructor.
     * @param $content
     * @param int $status
     * @param array $headers
     */
    public function __construct($content = null, int $status = 200, array $headers = [])
    {
        if ($content instanceof Throwable) {
            $this->exception = $content;
            parent::__construct($this->exception->getMessage(), $status, $headers);
        } else {
            parent::__construct(is_scalar($content) ? (string)$content : null, $status, $headers);
        }
    }

    /**
     * @param array $input
     * @param int $code
     * @param string|null $text
     * @param string|null $charset
     * @return $this
     */
    public function json(array $input, int $code, ?string $text = null, string $charset = null): Response
    {
        foreach ([
                     'cache-control' => ['no-cache, private'],
                     'content-type' => ['application/json'],
                 ] as $header => $values) {
            if (!$this->headers->has($header)) {
                $this->headers->add([$header => $values]);
            } else {
                $this->headers->set($header, $values);
            }
        }

        $this->setContent(json_encode($input));
        $this->setStatusCode($code, $text);

        if ($charset) {
            $this->setCharset($charset);
        }

        return $this;
    }

    /**
     * @param string $key
     * @param string|null $values
     * @param bool $replace
     */
    public function setHeader(string $key, $values, bool $replace = true)
    {
        if (!$values) {
            return;
        }

        if ($this->headers->has($key)) {

            return;
        }

        $this->headers->set($key,$values, $replace);
    }

    /**
     * @param object $response
     * @param string|null $text
     * @return bool
     */
    public function merge(object $response, string $text = null): bool
    {
        if (!($response instanceof SymfonyResponse)) {
            return false;
        }

        foreach ($response->headers->all() as $header => $values) {
            if (!$this->headers->has($header)) {
                $this->headers->add([$header => $values]);
            } else {
                $this->headers->set($header, $values);
            }
        }

        $content = $response->getContent();
        $this->setContent($content);

        $this->setStatusCode($response->getStatusCode(), $text);

        $charset = $response->getCharset();
        if ($charset) {
            $this->setCharset($charset);
        }

        return true;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers->all();
    }

    /**
     * @return Throwable|null
     */
    public function getException(): ?Throwable
    {
        return $this->exception instanceof Throwable ? $this->exception : null;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed|void
     */
    public function __call(string $name, array $arguments)
    {
        $this->exception = new Exception(__CLASS__.'::'.$name.' not found');
    }
}
