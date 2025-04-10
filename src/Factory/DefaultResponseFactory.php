<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Factory;

use Psr\Http\Message\ResponseFactoryInterface as PsrResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use GraphQL\Middleware\Contract\ResponseFactoryInterface;

final class DefaultResponseFactory implements ResponseFactoryInterface
{
    public function __construct(
        private readonly PsrResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function createResponse(array $data, int $status = 200, array $headers = []): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response = $response->withHeader('Content-Type', 'application/json');

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $body = $this->streamFactory->createStream(json_encode($data, JSON_THROW_ON_ERROR));
        return $response->withBody($body);
    }

    public function createErrorResponse(array $errors, int $status = 400): ResponseInterface
    {
        return $this->createResponse(['errors' => $errors], $status);
    }
}
