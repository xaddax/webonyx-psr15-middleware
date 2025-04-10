<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Contract;

interface TypeMapperInterface
{
    /**
     * Convert a GraphQL type to its PHP equivalent
     */
    public function toPhpType(string $graphqlType): string;

    /**
     * Convert a GraphQL type to its PHP DocBlock equivalent
     */
    public function toDocBlockType(string $graphqlType): string;
}
