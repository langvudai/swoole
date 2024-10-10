<?php

namespace SwooleBase\Foundation\Interfaces;

use Throwable;

interface ResponseInterface
{
    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;
    public const UNPROCESSABLE_ENTITY = 422;
    public const INTERNAL_SERVER_ERROR = 500;
    public const SERVICE_UNAVAILABLE = 503;

    /**
     * @param string $name
     * @param array $arguments
     */
    public function __call(string $name, array $arguments);

    public function merge(object $response, string $text = null): bool;

    public function setHeader(string $key, $values, bool $replace = true);

    public function getHeaders(): array;

    public function setContent(?string $content);

    public function getContent();

    public function getException(): ?Throwable;

    public function send();
}
