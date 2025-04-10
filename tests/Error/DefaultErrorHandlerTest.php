<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Error;

use GraphQL\Error\Error;
use GraphQL\Middleware\Error\DefaultErrorHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class DefaultErrorHandlerTest extends TestCase
{
    private DefaultErrorHandler $handler;
    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        $this->handler = new DefaultErrorHandler();
        $this->request = $this->createMock(ServerRequestInterface::class);
    }

    public function testHandleErrorWithBasicError(): void
    {
        $error = new Error('Test error');
        $result = $this->handler->handleError($error, $this->request);

        $this->assertEquals([
            'message' => 'Test error',
        ], $result);
    }

    public function testHandleErrorWithLocations(): void
    {
        $error = new Error(
            'Test error',
            null,
            null,
            [],
            [0],
            null,
            ['line' => 10, 'column' => 20]
        );

        /** @var array{message: string, locations: array<array{line: int, column: int}>} $result */
        $result = $this->handler->handleError($error, $this->request);
        assert(is_array($result));

        $this->assertArrayHasKey('locations', $result);
        $this->assertIsArray($result['locations']);
        $this->assertCount(1, $result['locations']);
        $this->assertEquals([
            'line' => 10,
            'column' => 20
        ], $result['locations'][0]);
    }

    public function testHandleErrorWithExtensions(): void
    {
        $extensions = ['code' => 'CUSTOM_ERROR', 'statusCode' => 400];
        $error = new Error(
            'Test error',
            null,
            null,
            [],
            null,
            null,
            $extensions
        );

        /** @var array{message: string, extensions: array<string, mixed>} $result */
        $result = $this->handler->handleError($error, $this->request);
        assert(is_array($result));

        $this->assertArrayHasKey('extensions', $result);
        $this->assertIsArray($result['extensions']);
        $this->assertEquals($extensions, $result['extensions']);
    }

    public function testGetStatusCodeWithDefaultValue(): void
    {
        $error = new Error('Test error');
        $statusCode = $this->handler->getStatusCode($error);

        $this->assertEquals(200, $statusCode);
    }

    public function testGetStatusCodeWithCustomValue(): void
    {
        $error = new Error(
            'Test error',
            null,
            null,
            [],
            null,
            null,
            ['statusCode' => 400]
        );
        $statusCode = $this->handler->getStatusCode($error);

        $this->assertEquals(400, $statusCode);
    }
}
