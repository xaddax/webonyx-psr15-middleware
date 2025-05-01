<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Test\Config;

use GraphQL\Middleware\Config\GeneratorConfig;
use PHPUnit\Framework\TestCase;

class GeneratorConfigTest extends TestCase
{
    private const VALID_CONFIG = [
        'entityConfig' => [
            'namespace' => 'App\\Entity',
            'fileLocation' => '/path/to/entities',
            'templatePath' => '/path/to/entity.template',
        ],
        'requestConfig' => [
            'namespace' => 'App\\Request',
            'fileLocation' => '/path/to/requests',
            'templatePath' => '/path/to/request.template',
        ],
        'resolverConfig' => [
            'namespace' => 'App\\Resolver',
            'fileLocation' => '/path/to/resolvers',
            'templatePath' => '/path/to/resolver.template',
        ],
        'typeMappings' => [
            'ID' => 'string',
            'String' => 'string',
            'Int' => 'int',
            'Float' => 'float',
            'Boolean' => 'bool',
        ],
        'customTypes' => [
            'DateTime' => [
                'type' => 'DateTimeImmutable',
                'imports' => ['DateTimeImmutable'],
            ],
        ],
        'isImmutable' => true,
        'hasStrictTypes' => true,
    ];

    public function testConstructorWithValidConfig(): void
    {
        $config = new GeneratorConfig(self::VALID_CONFIG);

        $this->assertSame(
            self::VALID_CONFIG['entityConfig'],
            $config->getEntityConfig()->toArray()
        );
        $this->assertSame(
            self::VALID_CONFIG['requestConfig'],
            $config->getRequestConfig()->toArray()
        );
        $this->assertSame(
            self::VALID_CONFIG['resolverConfig'],
            $config->getResolverConfig()->toArray()
        );
        $this->assertSame(self::VALID_CONFIG['typeMappings'], $config->getTypeMappings());
        $this->assertSame(self::VALID_CONFIG['customTypes'], $config->getCustomTypes());
        $this->assertSame(self::VALID_CONFIG['isImmutable'], $config->isImmutable());
        $this->assertSame(self::VALID_CONFIG['hasStrictTypes'], $config->hasStrictTypes());
    }

    public function testToArrayReturnsExpectedArray(): void
    {
        $config = new GeneratorConfig(self::VALID_CONFIG);

        $this->assertSame(self::VALID_CONFIG, $config->toArray());
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testConstructorThrowsExceptionForInvalidConfig(array $config, string $expectedMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new GeneratorConfig($config);
    }

    /**
     * @dataProvider invalidTypeMappingsProvider
     */
    public function testConstructorThrowsExceptionForInvalidTypeMappings(array $typeMappings, string $expectedMessage): void
    {
        $config = self::VALID_CONFIG;
        $config['typeMappings'] = $typeMappings;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new GeneratorConfig($config);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidConfigProvider(): array
    {
        $validConfig = self::VALID_CONFIG;
        return [
            'missing entityConfig' => [
                array_diff_key($validConfig, ['entityConfig' => null]),
                'entityConfig must be an array',
            ],
            'entityConfig not array' => [
                ['entityConfig' => 'invalid'] + $validConfig,
                'entityConfig must be an array',
            ],
            'missing requestConfig' => [
                array_diff_key($validConfig, ['requestConfig' => null]),
                'requestConfig must be an array',
            ],
            'requestConfig not array' => [
                ['requestConfig' => 'invalid'] + $validConfig,
                'requestConfig must be an array',
            ],
            'missing resolverConfig' => [
                array_diff_key($validConfig, ['resolverConfig' => null]),
                'resolverConfig must be an array',
            ],
            'resolverConfig not array' => [
                ['resolverConfig' => 'invalid'] + $validConfig,
                'resolverConfig must be an array',
            ],
            'missing typeMappings' => [
                array_diff_key($validConfig, ['typeMappings' => null]),
                'typeMappings must be an array',
            ],
            'typeMappings not array' => [
                ['typeMappings' => 'invalid'] + $validConfig,
                'typeMappings must be an array',
            ],
            'missing customTypes' => [
                array_diff_key($validConfig, ['customTypes' => null]),
                'customTypes must be an array',
            ],
            'customTypes not array' => [
                ['customTypes' => 'invalid'] + $validConfig,
                'customTypes must be an array',
            ],
            'missing isImmutable' => [
                array_diff_key($validConfig, ['isImmutable' => null]),
                'isImmutable must be a boolean',
            ],
            'isImmutable not boolean' => [
                ['isImmutable' => 'invalid'] + $validConfig,
                'isImmutable must be a boolean',
            ],
            'missing hasStrictTypes' => [
                array_diff_key($validConfig, ['hasStrictTypes' => null]),
                'hasStrictTypes must be a boolean',
            ],
            'hasStrictTypes not boolean' => [
                ['hasStrictTypes' => 'invalid'] + $validConfig,
                'hasStrictTypes must be a boolean',
            ],
        ];
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidTypeMappingsProvider(): array
    {
        return [
            'non-string key' => [
                ['123' => 'string'],
                'Type mapping keys must be strings',
            ],
            'non-string value' => [
                ['String' => 123],
                'Type mapping values must be strings',
            ],
        ];
    }
}
