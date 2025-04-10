<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests;

use GraphQL\Server\ServerConfig;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use GraphQL\Middleware\Factory\DefaultResponseFactory;
use GraphQL\Middleware\GraphQLMiddleware;
use GraphQL\Middleware\Contract\RequestContextInterface;
use GraphQL\Middleware\Contract\RequestPreprocessorInterface;
use PHPUnit\Framework\MockObject\MockObject;

class GraphQLMiddlewareTest extends TestCase
{
    private GraphQLMiddleware $middleware;
    private ServerConfig $serverConfig;
    private DefaultResponseFactory $responseFactory;
    private Psr17Factory $psr17Factory;

    protected function setUp(): void
    {
        $this->psr17Factory = new Psr17Factory();
        $this->responseFactory = new DefaultResponseFactory(
            $this->psr17Factory,
            $this->psr17Factory
        );

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'hello' => [
                        'type' => Type::string(),
                        'resolve' => fn () => 'Hello World!',
                    ],
                ],
            ]),
        ]);

        if (!$schema instanceof Schema) {
            throw new \RuntimeException('Schema cannot be null');
        }

        $this->serverConfig = new ServerConfig();
        $this->serverConfig->setSchema($schema);

        $this->middleware = new GraphQLMiddleware(
            $this->serverConfig,
            $this->responseFactory,
            ['application/json']
        );
    }

    public function testProcessesGraphQLRequest(): void
    {
        $requestData = [
            'query' => '{ hello }',
            'variables' => [],
            'operationName' => null,
        ];
        $encodedData = json_encode($requestData, JSON_THROW_ON_ERROR);


        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/json'],
            $encodedData
        );

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $this->middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $result = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('Hello World!', $result['data']['hello']);
    }

    public function testPassesNonGraphQLRequestToHandler(): void
    {
        $requestData = [
            'query' => '{hello}',
            'variables' => [],
            'operationName' => null,
        ];
        $encodedData = json_encode($requestData, JSON_THROW_ON_ERROR);
        $request = new ServerRequest('POST', '/graphql', [], $encodedData);

        $response = $this->psr17Factory->createResponse();

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $result = $this->middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }

    public function testHandlesInvalidJsonRequest(): void
    {
        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/json'],
            'not json'
        );

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(400, $response->getStatusCode());
        $result = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('errors', $result);
        $this->assertIsArray($result['errors']);
        $this->assertCount(1, $result['errors']);
        $this->assertArrayHasKey('message', $result['errors'][0]);
        $this->assertStringContainsString('Invalid JSON', $result['errors'][0]['message']);
    }

    public function testHandlesInvalidGraphQLQuery(): void
    {
        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/json'],
            json_encode(['query' => '{nonexistent}'], JSON_THROW_ON_ERROR)
        );

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('errors', $result);
        $this->assertStringContainsString('Cannot query field', $result['errors'][0]['message']);
    }

    public function testHandlesRequestWithoutQuery(): void
    {
        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/json'],
            json_encode(['variables' => []], JSON_THROW_ON_ERROR)
        );

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('errors', $result);
        $this->assertStringContainsString('query', $result['errors'][0]['message']);
    }

    public function testHandlesRequestWithPreprocessor(): void
    {
        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/json'],
            json_encode(['query' => '{ hello }'], JSON_THROW_ON_ERROR)
        );

        /** @var RequestPreprocessorInterface&MockObject $preprocessor */
        /** @var RequestPreprocessorInterface&MockObject $preprocessor */
        $preprocessor = $this->createMock(RequestPreprocessorInterface::class);
        $preprocessor->expects($this->once())
            ->method('process')
            ->willReturnCallback(function ($request) {
                return $request;
            });

        $middleware = new GraphQLMiddleware(
            $this->serverConfig,
            $this->responseFactory,
            ['application/json'],
            $preprocessor
        );

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);

        $response = $middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('Hello World!', $result['data']['hello']);
    }

    public function testHandlesPreprocessorError(): void
    {
        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/json'],
            json_encode(['query' => '{ hello }'], JSON_THROW_ON_ERROR)
        );

        /** @var RequestPreprocessorInterface&MockObject $preprocessor */
        /** @var RequestPreprocessorInterface&MockObject $preprocessor */
        $preprocessor = $this->createMock(RequestPreprocessorInterface::class);
        $preprocessor->expects($this->once())
            ->method('process')
            ->willThrowException(new \Exception('Unauthorized'));

        $middleware = new GraphQLMiddleware(
            $this->serverConfig,
            $this->responseFactory,
            ['application/json'],
            $preprocessor
        );

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);

        $response = $middleware->process($request, $handler);

        $this->assertEquals(401, $response->getStatusCode());
        $result = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals('Unauthorized', $result['errors']['message']);
    }

    public function testHandlesRequestWithRequestContext(): void
    {
        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/json'],
            json_encode(['query' => '{ hello }'], JSON_THROW_ON_ERROR)
        );

        /** @var RequestContextInterface&MockObject $context */
        $context = $this->createMock(RequestContextInterface::class);
        $context->expects($this->once())
            ->method('setRequest')
            ->willReturnCallback(function ($request) {
                return null;
            });

        $this->serverConfig->setContext($context);

        $middleware = new GraphQLMiddleware(
            $this->serverConfig,
            $this->responseFactory,
            ['application/json']
        );

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);

        $response = $middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('Hello World!', $result['data']['hello']);
    }

    public function testHandlesNonArrayJsonResponse(): void
    {
        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/json'],
            json_encode('not an object', JSON_THROW_ON_ERROR)
        );

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(400, $response->getStatusCode());
        $result = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals('Invalid JSON: Expected object or array', $result['errors'][0]['message']);
    }
}
