<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Generator;

use GraphQL\Middleware\Generator\DefaultTypeMapper;
use PHPUnit\Framework\TestCase;

class DefaultTypeMapperTest extends TestCase
{
    private DefaultTypeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new DefaultTypeMapper();
    }

    /**
     * @dataProvider typeConversionProvider
     */
    public function testConvertsTypes(string $graphqlType, string $expectedPhpType): void
    {
        $this->assertEquals($expectedPhpType, $this->mapper->toPhpType($graphqlType));
    }

    public static function typeConversionProvider(): array
    {
        return [
            ['String', 'string'],
            ['Int', 'int'],
            ['Float', 'float'],
            ['Boolean', 'bool'],
            ['ID', 'string'],
            ['CustomType', 'CustomType'],
            ['String|null', 'string|null'],
            ['CustomType|null', 'CustomType|null'],
            ['array<string>', 'array<string>'],
            ['array<CustomType>', 'array<CustomType>'],
        ];
    }

    public function testDocBlockTypeMatchesPhpType(): void
    {
        $type = 'CustomType|null';
        $this->assertEquals(
            $this->mapper->toPhpType($type),
            $this->mapper->toDocBlockType($type)
        );
    }
}
