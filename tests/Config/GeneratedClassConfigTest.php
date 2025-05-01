<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Test\Config;

use GraphQL\Middleware\Config\EntityConfig;
use GraphQL\Middleware\Config\RequestConfig;
use GraphQL\Middleware\Config\ResolverConfig;
use PHPUnit\Framework\TestCase;

class GeneratedClassConfigTest extends TestCase
{
    private const VALID_CONFIG = [
        'namespace' => 'App\\Test',
        'fileLocation' => '/path/to/file',
        'templatePath' => '/path/to/template',
    ];

    /**
     * @dataProvider configClassProvider
     */
    public function testConstructorWithValidConfig(string $configClass): void
    {
        $config = new $configClass(self::VALID_CONFIG);

        $this->assertSame(self::VALID_CONFIG['namespace'], $config->getNamespace());
        $this->assertSame(self::VALID_CONFIG['fileLocation'], $config->getFileLocation());
        $this->assertSame(self::VALID_CONFIG['templatePath'], $config->getTemplatePath());
    }

    /**
     * @dataProvider configClassProvider
     */
    public function testToArrayReturnsExpectedArray(string $configClass): void
    {
        $config = new $configClass(self::VALID_CONFIG);

        $this->assertSame(self::VALID_CONFIG, $config->toArray());
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testConstructorThrowsExceptionForInvalidConfig(array $config, string $expectedMessage): void
    {
        foreach ($this->configClassProvider() as $name => [$configClass]) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage($expectedMessage);

            new $configClass($config);
        }
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function configClassProvider(): array
    {
        return [
            'entity config' => [EntityConfig::class],
            'request config' => [RequestConfig::class],
            'resolver config' => [ResolverConfig::class],
        ];
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidConfigProvider(): array
    {
        return [
            'missing namespace' => [
                [
                    'fileLocation' => '/path/to/file',
                    'templatePath' => '/path/to/template',
                ],
                'namespace must be a string',
            ],
            'missing fileLocation' => [
                [
                    'namespace' => 'App\\Test',
                    'templatePath' => '/path/to/template',
                ],
                'fileLocation must be a string',
            ],
            'missing templatePath' => [
                [
                    'namespace' => 'App\\Test',
                    'fileLocation' => '/path/to/file',
                ],
                'templatePath must be a string',
            ],
            'namespace not string' => [
                [
                    'namespace' => 123,
                    'fileLocation' => '/path/to/file',
                    'templatePath' => '/path/to/template',
                ],
                'namespace must be a string',
            ],
            'fileLocation not string' => [
                [
                    'namespace' => 'App\\Test',
                    'fileLocation' => ['invalid'],
                    'templatePath' => '/path/to/template',
                ],
                'fileLocation must be a string',
            ],
            'templatePath not string' => [
                [
                    'namespace' => 'App\\Test',
                    'fileLocation' => '/path/to/file',
                    'templatePath' => false,
                ],
                'templatePath must be a string',
            ],
        ];
    }
}
