<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Factory;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use GraphQL\Middleware\Factory\DefaultResponseFactory;

class DefaultResponseFactoryTest extends TestCase
{
    private DefaultResponseFactory $factory;
    private Psr17Factory $psr17Factory;

    protected function setUp(): void
    {
        $this->psr17Factory = new Psr17Factory();
        $this->factory = new DefaultResponseFactory(
            $this->psr17Factory,
            $this->psr17Factory
        );
    }

    public function testCreateResponse(): void
    {
        $data = ['data' => ['hello' => 'world']];
        $response = $this->factory->createResponse($data);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals(json_encode($data), (string) $response->getBody());
    }

    public function testCreateResponseWithCustomStatus(): void
    {
        $data = ['data' => ['hello' => 'world']];
        $response = $this->factory->createResponse($data, 201);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testCreateResponseWithCustomHeaders(): void
    {
        $data = ['data' => ['hello' => 'world']];
        $headers = ['X-Custom' => 'value'];
        $response = $this->factory->createResponse($data, 200, $headers);

        $this->assertEquals('value', $response->getHeaderLine('X-Custom'));
    }

    public function testCreateErrorResponse(): void
    {
        $errors = [['message' => 'Something went wrong']];
        $response = $this->factory->createErrorResponse($errors);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals(json_encode(['errors' => $errors]), (string) $response->getBody());
    }

    public function testCreateErrorResponseWithCustomStatus(): void
    {
        $errors = [['message' => 'Not Found']];
        $response = $this->factory->createErrorResponse($errors, 404);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testResponseWithInvalidJson(): void
    {
        $this->expectException(\JsonException::class);

        $data = ['data' => fopen('php://memory', 'r')]; // Cannot be JSON encoded
        $this->factory->createResponse($data);
    }
}
