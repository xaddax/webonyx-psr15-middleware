<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Factory;

use GraphQL\Server\ServerConfig;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use GraphQL\Middleware\Factory\GraphQLMiddlewareFactory;
use GraphQL\Middleware\GraphQLMiddleware;
use GraphQL\Middleware\Contract\RequestPreprocessorInterface;

class GraphQLMiddlewareFactoryTest extends TestCase
{
    private GraphQLMiddlewareFactory $factory;
    private ServerConfig&\PHPUnit\Framework\MockObject\MockObject $serverConfig;
    private Psr17Factory $psr17Factory;
    private RequestPreprocessorInterface&\PHPUnit\Framework\MockObject\MockObject $preprocessor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverConfig = $this->createMock(ServerConfig::class);
        $this->psr17Factory = new Psr17Factory();
        $this->preprocessor = $this->createMock(RequestPreprocessorInterface::class);
    }

    public function testCreatesMiddlewareWithPreprocessor(): void
    {
        $this->factory = new GraphQLMiddlewareFactory(
            serverConfig: $this->serverConfig,
            responseFactory: $this->psr17Factory,
            streamFactory: $this->psr17Factory,
            requestPreprocessor: $this->preprocessor
        );

        $middleware = $this->factory->createMiddleware();

        $this->assertInstanceOf(GraphQLMiddleware::class, $middleware);
    }

    public function testCreatesMiddlewareWithoutPreprocessor(): void
    {
        $this->factory = new GraphQLMiddlewareFactory(
            serverConfig: $this->serverConfig,
            responseFactory: $this->psr17Factory,
            streamFactory: $this->psr17Factory
        );

        $middleware = $this->factory->createMiddleware();

        $this->assertInstanceOf(GraphQLMiddleware::class, $middleware);
    }

    public function testCreatesMiddlewareWithCustomHeaders(): void
    {
        $this->factory = new GraphQLMiddlewareFactory(
            serverConfig: $this->serverConfig,
            responseFactory: $this->psr17Factory,
            streamFactory: $this->psr17Factory,
            allowedHeaders: ['application/graphql']
        );

        $middleware = $this->factory->createMiddleware();

        $this->assertInstanceOf(GraphQLMiddleware::class, $middleware);
    }
}
