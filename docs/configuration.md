# Configuration

The middleware can be configured through a configuration array that supports various options.

## Basic Configuration

```php
$config = [
    'cache' => [
        // Enable schema caching
        'enabled' => true,
        
        // Cache directory path
        'directory' => '/path/to/cache',
        
        // Cache filenames
        'directory_change_filename' => 'schema-directory-cache.php',
        'schema_filename' => 'schema-cache.php'
    ],
    'schema' => [
        // Directories containing GraphQL schema files
        'directories' => [
            __DIR__ . '/schema'
        ],
        
        // Parser options for GraphQL schema
        'parser_options' => []
    ],
    'resolver' => [
        // Namespace for resolver classes
        'namespace' => 'App\\GraphQL\\Resolver',
        
        // Optional fallback resolver
        'fallback_resolver' => function($source, $args, $context, $info) {
            return $source[$info->fieldName] ?? null;
        }
    ]
];

$configuration = new DefaultConfiguration($config);
```

## Configuration Options

### Cache Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `cache.enabled` | bool | `false` | Enable schema caching |
| `cache.directory` | string | `sys_get_temp_dir() . '/graphql-cache'` | Cache directory path |
| `cache.directory_change_filename` | string | `'schema-directory-cache.php'` | Cache filename for directory changes |
| `cache.schema_filename` | string | `'schema-cache.php'` | Cache filename for schema |

### Schema Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `schema.directories` | array | `[]` | Directories containing GraphQL schema files |
| `schema.parser_options` | array | `[]` | Parser options for GraphQL schema |

### Resolver Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `resolver.namespace` | string | `'App\\GraphQL\\Resolver'` | Namespace for resolver classes |
| `resolver.fallback_resolver` | callable\|null | `null` | Fallback resolver for fields without specific resolvers |

## Custom Configuration

You can create a custom configuration class by implementing `SchemaConfigurationInterface`:

```php
use GraphQL\Middleware\Contract\SchemaConfigurationInterface;

class CustomConfiguration implements SchemaConfigurationInterface
{
    public function isCacheEnabled(): bool
    {
        // Custom implementation
    }

    public function getCacheDirectory(): string
    {
        // Custom implementation
    }

    public function getSchemaDirectories(): array
    {
        // Custom implementation
    }

    public function getParserOptions(): array
    {
        // Custom implementation
    }

    public function getDirectoryChangeFilename(): string
    {
        // Custom implementation
    }

    public function getSchemaFilename(): string
    {
        // Custom implementation
    }

    public function getResolverConfig(): array
    {
        // Custom implementation
    }
}
