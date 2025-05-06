<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Config;

use GraphQL\Middleware\Config\EntityConfig;
use PHPUnit\Framework\TestCase;

class GeneratedClassConfigTest extends TestCase
{
    private EntityConfig $config;

    protected function setUp(): void
    {
        $this->config = new EntityConfig([
            'namespace' => 'App\\Entity',
            'fileLocation' => '/path/to/entities',
            'templatePath' => '/path/to/template.php',
        ]);
    }

    public function testGetters(): void
    {
        $this->assertEquals('App\\Entity', $this->config->getNamespace());
        $this->assertEquals('/path/to/entities', $this->config->getFileLocation());
        $this->assertEquals('/path/to/template.php', $this->config->getTemplatePath());
    }

    public function testToArray(): void
    {
        $data = [
            'namespace' => 'App\\Entity',
            'fileLocation' => '/path/to/entities',
            'templatePath' => '/path/to/template.php',
        ];

        $this->assertEquals($data, $this->config->toArray());
    }

    public function testMissingNamespaceThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('namespace must be a string');
        new EntityConfig([
            // 'namespace' => 'App\\Entity',
            'fileLocation' => '/path/to/entities',
            'templatePath' => '/path/to/template.php',
        ]);
    }

    public function testInvalidNamespaceTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('namespace must be a string');
        new EntityConfig([
            'namespace' => 123,
            'fileLocation' => '/path/to/entities',
            'templatePath' => '/path/to/template.php',
        ]);
    }

    public function testMissingFileLocationThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('fileLocation must be a string');
        new EntityConfig([
            'namespace' => 'App\\Entity',
            // 'fileLocation' => '/path/to/entities',
            'templatePath' => '/path/to/template.php',
        ]);
    }

    public function testInvalidFileLocationTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('fileLocation must be a string');
        new EntityConfig([
            'namespace' => 'App\\Entity',
            'fileLocation' => 123,
            'templatePath' => '/path/to/template.php',
        ]);
    }

    public function testMissingTemplatePathThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('templatePath must be a string');
        new EntityConfig([
            'namespace' => 'App\\Entity',
            'fileLocation' => '/path/to/entities',
            // 'templatePath' => '/path/to/template.php',
        ]);
    }

    public function testInvalidTemplatePathTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('templatePath must be a string');
        new EntityConfig([
            'namespace' => 'App\\Entity',
            'fileLocation' => '/path/to/entities',
            'templatePath' => 123,
        ]);
    }
}
