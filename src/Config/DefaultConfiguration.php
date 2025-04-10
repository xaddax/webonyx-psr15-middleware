<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Config;

use GraphQL\Middleware\Contract\SchemaConfigurationInterface;

final class DefaultConfiguration implements SchemaConfigurationInterface
{
    public function __construct(
        private readonly array $config
    ) {
    }

    public function isCacheEnabled(): bool
    {
        return $this->config['cache']['enabled'] ?? false;
    }

    public function getCacheDirectory(): string
    {
        return $this->config['cache']['directory'] ?? sys_get_temp_dir() . '/graphql-cache';
    }

    public function getSchemaDirectories(): array
    {
        return $this->config['schema']['directories'] ?? [];
    }

    public function getParserOptions(): array
    {
        return $this->config['schema']['parser_options'] ?? [];
    }

    public function getDirectoryChangeFilename(): string
    {
        return $this->config['cache']['directory_change_filename'] ?? 'schema-directory-cache.php';
    }

    public function getSchemaFilename(): string
    {
        return $this->config['cache']['schema_filename'] ?? 'schema-cache.php';
    }

    public function getResolverConfig(): array
    {
        return $this->config['resolver'] ?? [];
    }
}
