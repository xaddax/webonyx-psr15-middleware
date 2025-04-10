<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Contract;

interface SchemaAnalyzerInterface
{
    /**
     * Get all resolver requirements from schema
     *
     * @return array<string, array{
     *   type: string,
     *   field: string,
     *   returnType: string,
     *   args: array<string, string>,
     *   description: string|null
     * }>
     */
    public function getResolverRequirements(): array;
}
