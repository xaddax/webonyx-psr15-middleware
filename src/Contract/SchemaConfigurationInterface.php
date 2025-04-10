<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Contract;

interface SchemaConfigurationInterface
{
    /**
     * Whether schema caching is enabled
     */
    public function isCacheEnabled(): bool;

    /**
     * Get the cache directory path
     */
    public function getCacheDirectory(): string;

    /**
     * Get the list of directories containing GraphQL schema files
     * @return string[]
     */
    public function getSchemaDirectories(): array;

    /**
     * Get GraphQL parser options
     */
    public function getParserOptions(): array;

    /**
     * Get the filename for directory change cache
     */
    public function getDirectoryChangeFilename(): string;

    /**
     * Get the filename for schema cache
     */
    public function getSchemaFilename(): string;
}
