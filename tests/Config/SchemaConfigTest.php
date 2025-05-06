<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Config;

use PHPUnit\Framework\TestCase;
use GraphQL\Middleware\Config\SchemaConfig;

class SchemaConfigTest extends TestCase
{
    public function testFullConfig(): void
    {
        $schemaDirectories = ['/path/to/schema'];
        $cacheEnabled = true;
        $cacheDirectory = '/tmp/test-cache';
        $directoryChangeFilename = 'test-directory-cache.php';
        $schemaFilename = 'test-schema-cache.php';
        $parserOptions = ['option1' => 'value1'];
        $resolverConfig = [
            'namespace' => 'Custom\\Resolver',
            'fallback_resolver' => function (
                $source,
                $args,
                $context,
                $info
            ) {
                return null;
            },
        ];
        $typeConfigDecorator = function () {
            return 'type';
        };
        $schemaOptions = ['opt' => 'val'];
        $fieldConfigDecorator = function () {
            return 'field';
        };

        $config = new SchemaConfig(
            $schemaDirectories,
            $cacheEnabled,
            $cacheDirectory,
            $directoryChangeFilename,
            $schemaFilename,
            $parserOptions,
            $resolverConfig,
            $typeConfigDecorator,
            $schemaOptions,
            $fieldConfigDecorator
        );

        $this->assertSame($schemaDirectories, $config->getSchemaDirectories());
        $this->assertTrue($config->isCacheEnabled());
        $this->assertSame($cacheDirectory, $config->getCacheDirectory());
        $this->assertSame($directoryChangeFilename, $config->getDirectoryChangeFilename());
        $this->assertSame($schemaFilename, $config->getSchemaFilename());
        $this->assertSame($parserOptions, $config->getParserOptions());
        $this->assertSame($resolverConfig, $config->getResolverConfig());
        $this->assertSame($typeConfigDecorator, $config->getTypeConfigDecorator());
        $this->assertSame($schemaOptions, $config->getSchemaOptions());
        $this->assertSame($fieldConfigDecorator, $config->getFieldConfigDecorator());
    }

    public function testDefaults(): void
    {
        $schemaDirectories = ['/default/path'];
        $config = new SchemaConfig($schemaDirectories);

        $this->assertSame($schemaDirectories, $config->getSchemaDirectories());
        $this->assertFalse($config->isCacheEnabled());
        $this->assertSame('cache', $config->getCacheDirectory());
        $this->assertSame('directory-changes.php', $config->getDirectoryChangeFilename());
        $this->assertSame('schema.php', $config->getSchemaFilename());
        $this->assertSame([], $config->getParserOptions());
        $this->assertSame([], $config->getResolverConfig());
        $this->assertNull($config->getTypeConfigDecorator());
        $this->assertSame([], $config->getSchemaOptions());
        $this->assertNull($config->getFieldConfigDecorator());
    }

    public function testEmptySchemaDirectories(): void
    {
        $config = new SchemaConfig([]);
        $this->assertSame([], $config->getSchemaDirectories());
    }
}
