<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Factory;

use Nyholm\Psr7\Factory\Psr17Factory;
use JsonException;
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
        $response = $this->factory->createResponse();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCreateResponseWithData(): void
    {
        $data = ['data' => ['hello' => 'world']];
        $response = $this->factory->createResponseWithData($data);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals(json_encode($data, JSON_THROW_ON_ERROR), (string) $response->getBody());
    }

    public function testCreateResponseWithCustomStatus(): void
    {
        $data = ['data' => ['hello' => 'world']];
        $response = $this->factory->createResponseWithData($data, 201);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testCreateResponseWithCustomHeaders(): void
    {
        $data = ['data' => ['hello' => 'world']];
        $headers = ['X-Custom' => 'value'];
        $response = $this->factory->createResponseWithData($data, 200, $headers);

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
        $resource = fopen('php://memory', 'r');
        $this->expectException(JsonException::class);
        try {
            $this->factory->createResponseWithData(['data' => $resource]);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }
}
