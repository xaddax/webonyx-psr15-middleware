<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Configuration;

use GraphQL\Middleware\Contract\SchemaConfigurationInterface;

class SchemaConfiguration implements SchemaConfigurationInterface
{
    public function __construct(
        private readonly array $schemaDirectories,
        private readonly bool $cacheEnabled = false,
        private readonly string $cacheDirectory = 'cache',
        private readonly string $directoryChangeFilename = 'directory-changes.php',
        private readonly string $schemaFilename = 'schema.php',
        private readonly array $parserOptions = [],
        private readonly array $resolverConfig = []
    ) {
    }

    public function getSchemaDirectories(): array
    {
        return $this->schemaDirectories;
    }

    public function isCacheEnabled(): bool
    {
        return $this->cacheEnabled;
    }

    public function getCacheDirectory(): string
    {
        return $this->cacheDirectory;
    }

    public function getDirectoryChangeFilename(): string
    {
        return $this->directoryChangeFilename;
    }

    public function getSchemaFilename(): string
    {
        return $this->schemaFilename;
    }

    public function getParserOptions(): array
    {
        return $this->parserOptions;
    }

    /**
     * @return array{namespace?: string, fallback_resolver?: callable}
     */
    public function getResolverConfig(): array
    {
        return $this->resolverConfig;
    }
}
