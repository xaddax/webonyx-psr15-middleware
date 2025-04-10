<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Config;

use PHPUnit\Framework\TestCase;
use GraphQL\Middleware\Config\DefaultConfiguration;

class DefaultConfigurationTest extends TestCase
{
    private DefaultConfiguration $config;
    private array $testConfig;

    protected function setUp(): void
    {
        $this->testConfig = [
            'cache' => [
                'enabled' => true,
                'directory' => '/tmp/test-cache',
                'directory_change_filename' => 'test-directory-cache.php',
                'schema_filename' => 'test-schema-cache.php',
            ],
            'schema' => [
                'directories' => ['/path/to/schema'],
                'parser_options' => ['option1' => 'value1'],
            ],
            'resolver' => [
                'namespace' => 'Custom\\Resolver',
                'fallback_resolver' => function ($source, $args, $context, $info) {
                    return null;
                }
            ],
        ];

        $this->config = new DefaultConfiguration($this->testConfig);
    }

    public function testIsCacheEnabled(): void
    {
        $this->assertTrue($this->config->isCacheEnabled());

        $configWithoutCache = new DefaultConfiguration([]);
        $this->assertFalse($configWithoutCache->isCacheEnabled());
    }

    public function testGetCacheDirectory(): void
    {
        $this->assertEquals('/tmp/test-cache', $this->config->getCacheDirectory());

        $configWithoutCache = new DefaultConfiguration([]);
        $this->assertEquals(sys_get_temp_dir() . '/graphql-cache', $configWithoutCache->getCacheDirectory());
    }

    public function testGetSchemaDirectories(): void
    {
        $this->assertEquals(['/path/to/schema'], $this->config->getSchemaDirectories());

        $configWithoutSchema = new DefaultConfiguration([]);
        $this->assertEquals([], $configWithoutSchema->getSchemaDirectories());
    }

    public function testGetParserOptions(): void
    {
        $this->assertEquals(['option1' => 'value1'], $this->config->getParserOptions());

        $configWithoutOptions = new DefaultConfiguration([]);
        $this->assertEquals([], $configWithoutOptions->getParserOptions());
    }

    public function testGetDirectoryChangeFilename(): void
    {
        $this->assertEquals('test-directory-cache.php', $this->config->getDirectoryChangeFilename());

        $configWithoutFilename = new DefaultConfiguration([]);
        $this->assertEquals('schema-directory-cache.php', $configWithoutFilename->getDirectoryChangeFilename());
    }

    public function testGetSchemaFilename(): void
    {
        $this->assertEquals('test-schema-cache.php', $this->config->getSchemaFilename());

        $configWithoutFilename = new DefaultConfiguration([]);
        $this->assertEquals('schema-cache.php', $configWithoutFilename->getSchemaFilename());
    }

    public function testGetResolverConfig(): void
    {
        $resolverConfig = $this->config->getResolverConfig();
        $this->assertNotEmpty($resolverConfig);
        $this->assertTrue(array_key_exists('namespace', $resolverConfig));
        $this->assertTrue(array_key_exists('fallback_resolver', $resolverConfig));

        if (array_key_exists('namespace', $resolverConfig)) {
            $this->assertEquals('Custom\Resolver', $resolverConfig['namespace']);
        }
        if (array_key_exists('fallback_resolver', $resolverConfig)) {
            $this->assertIsCallable($resolverConfig['fallback_resolver']);
        }

        $configWithoutResolver = new DefaultConfiguration([]);
        $this->assertEquals([], $configWithoutResolver->getResolverConfig());
    }
}
