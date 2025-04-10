<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Generator;

use GraphQL\Middleware\Contract\TypeMapperInterface;

class DefaultTypeMapper implements TypeMapperInterface
{
    private const TYPE_MAP = [
        'String' => 'string',
        'Int' => 'int',
        'Float' => 'float',
        'Boolean' => 'bool',
        'ID' => 'string',
    ];

    public function toPhpType(string $graphqlType): string
    {
        // Handle arrays
        if (str_starts_with($graphqlType, 'array<')) {
            return $graphqlType;
        }

        // Handle nullable types
        if (str_ends_with($graphqlType, '|null')) {
            $baseType = substr($graphqlType, 0, -5);
            return (self::TYPE_MAP[$baseType] ?? $baseType) . '|null';
        }

        return self::TYPE_MAP[$graphqlType] ?? $graphqlType;
    }

    public function toDocBlockType(string $graphqlType): string
    {
        return $this->toPhpType($graphqlType);
    }
}
