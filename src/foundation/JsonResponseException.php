<?php

namespace SwooleBase\Foundation;

use RuntimeException;
use SwooleBase\Foundation\Http\Response;
use SwooleBase\Foundation\Interfaces\ExceptionHandler;
use Symfony\Component\HttpFoundation\JsonResponse;

class JsonResponseException extends RuntimeException implements ExceptionHandler
{
    private $response;

    /**
     * JsonResponseException constructor.
     * @param JsonResponse $response
     */
    public function __construct(JsonResponse $response)
    {
        $this->response = $response;
        parent::__construct($response->getContent());
    }

    /**
     * @return Response
     */
    public function handler(): Response
    {
        $response = new Response($this);
        $response->merge($this->response);

        return $response;
    }
}
