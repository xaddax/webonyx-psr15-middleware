<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Error;

use GraphQL\Error\ClientAware;
use GraphQL\Middleware\Error\GraphQLException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GraphQLExceptionTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $message = 'Test error message';
        $isClientSafe = false;
        $category = 'test_category';
        $extensions = ['key' => 'value'];
        $code = 123;
        $previous = new RuntimeException('Previous error');

        $exception = new GraphQLException(
            $message,
            $isClientSafe,
            $category,
            $extensions,
            $code,
            $previous
        );

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertInstanceOf(ClientAware::class, $exception);
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame($isClientSafe, $exception->isClientSafe());
        $this->assertSame($category, $exception->getCategory());
        $this->assertSame($extensions, $exception->getExtensions());
    }

    public function testDefaultValues(): void
    {
        $message = 'Test error message';
        $exception = new GraphQLException($message);

        $this->assertTrue($exception->isClientSafe());
        $this->assertNull($exception->getCategory());
        $this->assertSame([], $exception->getExtensions());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }
}
