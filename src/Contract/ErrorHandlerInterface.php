<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Contract;

use GraphQL\Error\Error;
use Psr\Http\Message\ServerRequestInterface;

interface ErrorHandlerInterface
{
    /**
     * Handle a GraphQL error and return an array that will be included in the response.
     *
     * @param Error $error The GraphQL error to handle
     * @param ServerRequestInterface $request The original request
     * @return array{
     *     message: string,
     *     locations?: array<array{line: int, column: int}>,
     *     path?: array<int|string>,
     *     extensions?: array<string, mixed>
     * }
     */
    public function handleError(Error $error, ServerRequestInterface $request): array;

    /**
     * Get the HTTP status code for the response.
     * This allows different errors to result in different status codes.
     */
    public function getStatusCode(Error $error): int;
}
