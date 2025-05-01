<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase
{
    protected function mockClassExists(string $class): void
    {
        $mock = $this->getMockBuilder('stdClass')
            ->addMethods(['class_exists'])
            ->getMock();
        $mock->method('class_exists')
            ->willReturnCallback(function ($testClass) use ($class): bool {
                return $testClass === $class;
            });
        $GLOBALS['mock_class_exists'] = $mock;
    }
}
