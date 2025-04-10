<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Factory;

use Psr\Http\Message\ResponseFactoryInterface as PsrResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use GraphQL\Middleware\Contract\ResponseFactoryInterface;

final class DefaultResponseFactory implements ResponseFactoryInterface
{
    public function __construct(
        private readonly PsrResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function createResponse(int $code = 200): ResponseInterface
    {
        return $this->responseFactory->createResponse($code);
    }

    public function createStream(string $content): StreamInterface
    {
        $stream = $this->streamFactory->createStream($content);
        $stream->rewind();
        return $stream;
    }

    public function createResponseWithData(array $data, int $status = 200, array $headers = []): ResponseInterface
    {
        $response = $this->createResponse($status);
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $response = $response->withHeader('Content-Type', 'application/json');
        $response = $response->withBody($this->createStream(
            json_encode($data, JSON_THROW_ON_ERROR)
        ));

        return $response;
    }

    public function createErrorResponse(array $errors, int $status = 400): ResponseInterface
    {
        return $this->createResponseWithData(['errors' => $errors], $status);
    }
}
