<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Contract;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

interface ResponseFactoryInterface
{
    /**
     * Create a new response.
     *
     * @param int $code HTTP status code
     * @return ResponseInterface
     */
    public function createResponse(int $code = 200): ResponseInterface;

    /**
     * Create a new stream from a string.
     *
     * @param string $content
     * @return StreamInterface
     */
    public function createStream(string $content): StreamInterface;

    /**
     * Create a JSON response with the given data
     */
    public function createResponseWithData(array $data, int $status = 200, array $headers = []): ResponseInterface;

    /**
     * Create an error response with the given error data
     */
    public function createErrorResponse(array $errors, int $status = 400): ResponseInterface;
}
