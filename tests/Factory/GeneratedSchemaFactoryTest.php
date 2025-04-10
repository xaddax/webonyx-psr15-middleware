<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Factory;

use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use GraphQL\Middleware\Contract\SchemaConfigurationInterface;
use GraphQL\Middleware\Factory\GeneratedSchemaFactory;

class GeneratedSchemaFactoryTest extends TestCase
{
    private const SCHEMA_DIR = __DIR__ . '/../Fixture/schema';
    private const CACHE_DIR = __DIR__ . '/../Fixture/cache';
    private GeneratedSchemaFactory $factory;
    private SchemaConfigurationInterface&\PHPUnit\Framework\MockObject\MockObject $config;

    protected function setUp(): void
    {
        parent::setUp();

        // Create cache directory if it doesn't exist
        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR, 0777, true);
        }

        $this->config = $this->createMock(SchemaConfigurationInterface::class);
        $this->config->expects($this->any())->method('isCacheEnabled')->willReturn(true);
        $this->config->expects($this->any())->method('getSchemaDirectories')->willReturn([self::SCHEMA_DIR]);
        $this->config->expects($this->any())->method('getCacheDirectory')->willReturn(self::CACHE_DIR);
        $dirChangeFile = 'schema-directory-cache.php';
        $this->config->expects($this->any())
            ->method('getDirectoryChangeFilename')
            ->willReturn($dirChangeFile);
        $this->config->expects($this->any())->method('getSchemaFilename')->willReturn('schema-cache.php');
        $this->config->expects($this->any())->method('getParserOptions')->willReturn([]);

        $this->factory = new GeneratedSchemaFactory($this->config);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up cache files
        $cacheFiles = glob(self::CACHE_DIR . '/*.php');
        if ($cacheFiles === false) {
            throw new \RuntimeException('Failed to list directory contents');
        }
        foreach ($cacheFiles as $file) {
            unlink($file);
        }
    }

    public function testCreatesSchemaSuccessfully(): void
    {
        $schema = $this->factory->createSchema();
        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function testCreatesSchemaCacheFile(): void
    {
        $cacheFile = self::CACHE_DIR . '/schema-cache.php';
        $this->assertFileDoesNotExist($cacheFile);

        $this->factory->createSchema();

        $this->assertFileExists($cacheFile);
    }

    public function testUsesExistingCacheFile(): void
    {
        // Create schema to generate cache
        $this->factory->createSchema();

        // Get cache file modification time
        $cacheFile = self::CACHE_DIR . '/schema-cache.php';
        $firstMTime = filemtime($cacheFile);

        // Wait a second to ensure different modification time
        sleep(1);

        // Create schema again
        $this->factory->createSchema();

        // Cache file should not have been modified
        $this->assertEquals($firstMTime, filemtime($cacheFile));
    }
}
