<?php

namespace SwooleBase\Foundation\Interfaces;

interface HasResponse
{
    /**
     * @param ResponseInterface $response
     * @return void
     */
    public function respond(ResponseInterface $response);
}
