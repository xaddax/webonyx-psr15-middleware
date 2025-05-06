<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Config;

class SchemaConfig
{
    public function __construct(
        private readonly array $schemaDirectories,
        private readonly bool $cacheEnabled = false,
        private readonly string $cacheDirectory = 'cache',
        private readonly string $directoryChangeFilename = 'directory-changes.php',
        private readonly string $schemaFilename = 'schema.php',
        private readonly array $parserOptions = [],
        private readonly array $resolverConfig = [],
        private readonly mixed $typeConfigDecorator = null,
        private readonly array $schemaOptions = [],
        private readonly mixed $fieldConfigDecorator = null
    ) {
    }

    public function getCacheDirectory(): string
    {
        return $this->cacheDirectory;
    }

    public function getDirectoryChangeFilename(): string
    {
        return $this->directoryChangeFilename;
    }

    public function getFieldConfigDecorator(): callable|null
    {
        return is_callable($this->fieldConfigDecorator) ? $this->fieldConfigDecorator : null;
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

    public function getSchemaDirectories(): array
    {
        return $this->schemaDirectories;
    }

    public function getSchemaFilename(): string
    {
        return $this->schemaFilename;
    }

    public function getSchemaOptions(): array
    {
        return $this->schemaOptions;
    }

    public function getTypeConfigDecorator(): callable|null
    {
        return is_callable($this->typeConfigDecorator) ? $this->typeConfigDecorator : null;
    }

    public function isCacheEnabled(): bool
    {
        return $this->cacheEnabled;
    }
}
