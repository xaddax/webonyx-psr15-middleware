<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Contract;

use Psr\Http\Message\ResponseInterface;

interface ResponseFactoryInterface
{
    /**
     * Create a JSON response with the given data
     */
    public function createResponse(array $data, int $status = 200, array $headers = []): ResponseInterface;

    /**
     * Create an error response with the given error data
     */
    public function createErrorResponse(array $errors, int $status = 400): ResponseInterface;
}
