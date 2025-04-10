<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Factory;

use GraphQL\Server\ServerConfig;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use GraphQL\Middleware\Contract\ResponseFactoryInterface as GraphQLResponseFactoryInterface;
use GraphQL\Middleware\GraphQLMiddleware;
use GraphQL\Middleware\Contract\RequestPreprocessorInterface;

final class GraphQLMiddlewareFactory
{
    public function __construct(
        private readonly ServerConfig $serverConfig,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly array $allowedHeaders = ['application/json', 'application/graphql'],
        private readonly ?RequestPreprocessorInterface $requestPreprocessor = null,
    ) {
    }

    public function createMiddleware(): GraphQLMiddleware
    {
        $responseFactory = new DefaultResponseFactory(
            $this->responseFactory,
            $this->streamFactory
        );

        return new GraphQLMiddleware(
            $this->serverConfig,
            $responseFactory,
            $this->allowedHeaders,
            $this->requestPreprocessor
        );
    }
}
