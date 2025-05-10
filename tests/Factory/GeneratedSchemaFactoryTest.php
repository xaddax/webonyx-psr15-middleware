<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Factory;

use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use GraphQL\Middleware\Config\SchemaConfig;
use GraphQL\Middleware\Factory\GeneratedSchemaFactory;
use Test\Fixture\TestContainer;

class GeneratedSchemaFactoryTest extends TestCase
{
    private const SCHEMA_DIR = __DIR__ . '/../Fixture/schema';
    private const CACHE_DIR = __DIR__ . '/../Fixture/cache';
    private GeneratedSchemaFactory $factory;
    private SchemaConfig&\PHPUnit\Framework\MockObject\MockObject $config;
    private TestContainer $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Create cache directory if it doesn't exist
        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR, 0777, true);
        }

        $this->config = $this->createMock(SchemaConfig::class);
        $this->config->expects($this->any())->method('isCacheEnabled')->willReturn(true);
        $this->config->expects($this->any())->method('getSchemaDirectories')->willReturn([self::SCHEMA_DIR]);
        $this->config->expects($this->any())->method('getCacheDirectory')->willReturn(self::CACHE_DIR);
        $dirChangeFile = 'schema-directory-cache.php';
        $this->config->expects($this->any())
            ->method('getDirectoryChangeFilename')
            ->willReturn($dirChangeFile);
        $this->config->expects($this->any())->method('getSchemaFilename')->willReturn('schema-cache.php');
        $this->config->expects($this->any())->method('getParserOptions')->willReturn([]);

        $this->factory = new GeneratedSchemaFactory();
        $this->container = new TestContainer([
            SchemaConfig::class => $this->config,
        ]);
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
        $schema = ($this->factory)($this->container);
        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function testCreatesSchemaCacheFile(): void
    {
        $cacheFile = self::CACHE_DIR . '/schema-cache.php';
        $this->assertFileDoesNotExist($cacheFile);

        ($this->factory)($this->container);

        $this->assertFileExists($cacheFile);
    }

    public function testUsesExistingCacheFile(): void
    {
        // Create schema to generate cache
        ($this->factory)($this->container);

        // Get cache file modification time
        $cacheFile = self::CACHE_DIR . '/schema-cache.php';
        $firstMTime = filemtime($cacheFile);

        // Wait a second to ensure different modification time
        sleep(1);

        // Create schema again
        ($this->factory)($this->container);

        // Cache file should not have been modified
        $this->assertEquals($firstMTime, filemtime($cacheFile));
    }

    public function testHandlesDirectoryChanges(): void
    {
        // Create schema to generate cache
        ($this->factory)($this->container);

        // Get cache file modification time
        $cacheFile = self::CACHE_DIR . '/schema-cache.php';
        $firstMTime = filemtime($cacheFile);

        // Wait a second to ensure different modification time
        sleep(1);

        // Create a new schema file
        $newSchemaFile = self::SCHEMA_DIR . '/additional.graphql';
        file_put_contents($newSchemaFile, 'type Additional { id: ID! }');

        try {
            // Create schema again
            ($this->factory)($this->container);

            // Cache file should have been modified
            $this->assertGreaterThan($firstMTime, filemtime($cacheFile));
        } finally {
            unlink($newSchemaFile);
        }
    }

    public function testHandlesInvalidDirectory(): void
    {
        // Create a temporary schema file in test directory
        $schemaFile = self::SCHEMA_DIR . '/test.graphql';
        file_put_contents($schemaFile, 'type TestQuery { test: String }');

        try {
            $this->config = $this
                ->createMock(SchemaConfig::class);
            $this->config->expects($this->any())
                ->method('isCacheEnabled')
                ->willReturn(true);
            $this->config
                ->expects($this->any())
                ->method('getSchemaDirectories')
                ->willReturn([
                    '/nonexistent',
                    self::SCHEMA_DIR
                ]);
            $this->config->expects($this->any())
                ->method('getCacheDirectory')
                ->willReturn(self::CACHE_DIR);
            $this->config->expects($this->any())
                ->method('getDirectoryChangeFilename')
                ->willReturn('schema-directory-cache.php');
            $this->config->expects($this->any())
                ->method('getSchemaFilename')
                ->willReturn('schema-cache.php');
            $this->config->expects($this->any())
                ->method('getParserOptions')
                ->willReturn([]);

            $factory = new GeneratedSchemaFactory();
            $container = new TestContainer([
                SchemaConfig::class => $this->config,
            ]);
            $schema = $factory($container);
            $this->assertInstanceOf(Schema::class, $schema);
        } finally {
            unlink($schemaFile);
        }
    }

    public function testCreateSchemaWithoutCache(): void
    {
        $this->config = $this->createMock(SchemaConfig::class);
        $this->config->expects($this->any())
            ->method('isCacheEnabled')
            ->willReturn(false);
        $this->config->expects($this->any())
            ->method('getSchemaDirectories')
            ->willReturn([self::SCHEMA_DIR]);
        $this->config->expects($this->any())
            ->method('getCacheDirectory')
            ->willReturn(self::CACHE_DIR);
        $this->config->expects($this->any())
            ->method('getDirectoryChangeFilename')
            ->willReturn('schema-directory-cache.php');
        $this->config->expects($this->any())
            ->method('getSchemaFilename')
            ->willReturn('schema-cache.php');
        $this->config->expects($this->any())
            ->method('getParserOptions')
            ->willReturn([]);

        $factory = new GeneratedSchemaFactory();
        $container = new TestContainer([
            SchemaConfig::class => $this->config,
        ]);
        $schema = $factory($container);
        $this->assertInstanceOf(Schema::class, $schema);

        $cacheFile = self::CACHE_DIR . '/schema-cache.php';
        $this->assertFileDoesNotExist($cacheFile);
    }

    public function testThrowsExceptionOnInvalidSchema(): void
    {
        $schemaFile = self::SCHEMA_DIR . '/invalid.graphql';
        file_put_contents($schemaFile, 'type InvalidType Query');

        try {
            $this->expectException(\GraphQL\Error\SyntaxError::class);
            ($this->factory)($this->container);
        } finally {
            unlink($schemaFile);
        }
    }

    public function testHandlesUnwritableCacheDirectory(): void
    {
        $unwritableDir = self::CACHE_DIR . '/unwritable';
        if (!is_dir($unwritableDir)) {
            mkdir($unwritableDir, 0777, true);
        }
        chmod($unwritableDir, 0444);

        try {
            $this->config = $this->createMock(SchemaConfig::class);
            $this->config->expects($this->any())
                ->method('isCacheEnabled')
                ->willReturn(true);
            $this->config->expects($this->any())
                ->method('getSchemaDirectories')
                ->willReturn([self::SCHEMA_DIR]);
            $this->config->expects($this->any())
                ->method('getCacheDirectory')
                ->willReturn($unwritableDir);
            $this->config->expects($this->any())
                ->method('getDirectoryChangeFilename')
                ->willReturn('schema-directory-cache.php');
            $this->config->expects($this->any())
                ->method('getSchemaFilename')
                ->willReturn('schema-cache.php');
            $this->config->expects($this->any())
                ->method('getParserOptions')
                ->willReturn([]);

            $factory = new GeneratedSchemaFactory();
            $container = new TestContainer([
                SchemaConfig::class => $this->config,
            ]);
            $schema = $factory($container);
            $this->assertInstanceOf(Schema::class, $schema);
        } finally {
            chmod($unwritableDir, 0777);
            rmdir($unwritableDir);
        }
    }

    public function testHandlesEmptyDirectoryCache(): void
    {
        $dirCacheFile = self::CACHE_DIR . '/schema-directory-cache.php';
        file_put_contents($dirCacheFile, "<?php\nreturn ['test' => 123];\n");

        $schema = ($this->factory)($this->container);
        $this->assertInstanceOf(Schema::class, $schema);
    }
}
