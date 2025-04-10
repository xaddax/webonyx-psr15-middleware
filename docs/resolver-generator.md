# Resolver Generator

The Resolver Generator is a tool that automatically creates resolver classes based on your GraphQL schema. It analyzes your schema to identify fields that need resolvers and generates the appropriate PHP classes.

## Basic Usage

```php
use GraphQL\Middleware\Generator\ResolverGenerator;

$generator = new ResolverGenerator(
    $schemaFactory,      // Your existing GeneratedSchemaFactory instance
    'App\\GraphQL',      // Base namespace for generated resolvers
    __DIR__ . '/src'     // Output directory for resolver files
);

$generator->generateAll();
```

## How It Works

1. The generator analyzes your GraphQL schema using the AST (Abstract Syntax Tree)
2. It identifies fields that need resolvers:
   - Fields with arguments
   - Fields returning non-scalar types
   - Fields returning lists
3. For each field needing a resolver, it generates a PHP class:
   - Implements `ResolverInterface`
   - Includes proper type hints and docblocks
   - Preserves schema descriptions as PHP docblocks
   - Adds argument documentation

## Generated Code Example

For a schema like:

```graphql
type Query {
    """
    Get a user by ID
    """
    user(id: ID!): User
}

type User {
    id: ID!
    name: String!
    posts: [Post!]
}
```

The generator creates:

```php
<?php

declare(strict_types=1);

namespace App\GraphQL;

use GraphQL\Middleware\Contract\ResolverInterface;

/**
 * Resolver for Query.user
 *
 * Get a user by ID
 *
 * @param array $args
 *   - id: string
 */
class QueryUserResolver implements ResolverInterface
{
    public function __invoke($source, array $args, $context, $info): User|null
    {
        // TODO: Implement resolver
        throw new \RuntimeException('Not implemented');
    }
}
```

## Customization

### Custom Template Engine

You can provide your own template engine by implementing `TemplateEngineInterface`:

```php
class MyTemplateEngine implements TemplateEngineInterface
{
    public function render(string $template, array $variables): string
    {
        // Your custom template rendering logic
    }

    public function supports(string $template): bool
    {
        return true; // Or your custom logic
    }
}

$generator = new ResolverGenerator(
    $schemaFactory,
    'App\\GraphQL',
    __DIR__ . '/src',
    new MyTemplateEngine()
);
```

### Custom Type Mapping

You can customize how GraphQL types are mapped to PHP types:

```php
class MyTypeMapper implements TypeMapperInterface
{
    public function toPhpType(string $graphqlType): string
    {
        // Your custom type mapping logic
    }

    public function toDocBlockType(string $graphqlType): string
    {
        // Your custom docblock type mapping
    }
}

$generator = new ResolverGenerator(
    $schemaFactory,
    'App\\GraphQL',
    __DIR__ . '/src',
    null,
    new MyTypeMapper()
);
```

## Configuration

The generator uses several components that can be configured:

1. **Schema Factory**: Provides the GraphQL schema AST
2. **Namespace**: Base namespace for generated resolver classes
3. **Output Directory**: Where resolver files will be created
4. **Template Engine**: How resolver classes are rendered (optional)
5. **Type Mapper**: How GraphQL types are converted to PHP types (optional)

## Best Practices

1. **Version Control**: Generated resolvers should be committed to version control
2. **Customization**: Don't modify generated resolvers directly; instead:
   - Create base classes for common functionality
   - Use dependency injection for services
   - Add traits for shared behavior

3. **Organization**: Keep generated resolvers in a dedicated directory
4. **Testing**: Write tests for your resolver implementations

## Error Handling

The generator throws exceptions in these cases:

- `GeneratorException`: Base exception class
- `TemplateRenderException`: When template rendering fails

## Resolver Requirements

The generator analyzes your schema to determine which fields need resolvers. A resolver requirement is created when:

1. A field returns a non-scalar type (e.g., `User`, `Product`)
2. A field returns a list of any type (e.g., `[String]`, `[User]`)
3. A field has arguments, regardless of return type

The resolver requirements are stored in the following format:

```php
[
    'Query.user' => [
        'type' => 'Query',           // Parent type
        'field' => 'user',           // Field name
        'returnType' => 'User',      // PHP return type
        'args' => [                  // Field arguments
            'id' => 'string'         // Argument name => PHP type
        ],
        'description' => '...'       // Field description if any
    ]
]
```

### Scalar Type Handling

The following types are considered scalar and do not generate resolvers unless they have arguments:

1. Built-in scalars: `String`, `Int`, `Float`, `Boolean`, `ID`
2. Custom scalar types (e.g., `DateTime`, `Email`)
3. Enums

## Examples

### Lists and Connections

```graphql
type Query {
    users(first: Int): [User!]!      # Generates resolver (list + args)
    posts: [Post!]!                  # Generates resolver (list)
    tags: [String!]!                 # Generates resolver (list)
}
```

### Custom Scalars

```graphql
scalar DateTime

type User {
    id: ID!                         # No resolver (scalar)
    name: String!                   # No resolver (scalar)
    createdAt: DateTime!            # No resolver (custom scalar)
    posts(after: String): [Post!]!  # Generates resolver (list + args)
}
```

### Interfaces and Unions

```graphql
interface Node {
    id: ID!
}

type User implements Node {
    id: ID!
    name: String!
}

union SearchResult = User | Post

type Query {
    node(id: ID!): Node            # Generates resolver (interface + args)
    search(query: String!): SearchResult  # Generates resolver (union + args)
}
```

## Troubleshooting

### Common Issues

1. **No Resolvers Generated**
   - Check if your fields return non-scalar types
   - Verify that your schema is valid
   - Look for syntax errors in your schema

2. **Wrong PHP Types**
   - Implement a custom `TypeMapperInterface`
   - Check your scalar type definitions
   - Verify namespace imports

3. **File Generation Issues**
   - Ensure output directory is writable
   - Check file permissions
   - Verify namespace configuration

### Debugging

Enable debug mode in your template engine:

```php
class DebugTemplateEngine implements TemplateEngineInterface
{
    public function render(string $template, array $variables): string
    {
        // Log variables for debugging
        error_log(json_encode($variables, JSON_PRETTY_PRINT));
        return parent::render($template, $variables);
    }
}
```

## Integration with Build Process

You can integrate the generator into your build process:

```php
// In your build script
$generator = new ResolverGenerator(/* ... */);

try {
    $generator->generateAll();
} catch (GeneratorException $e) {
    echo "Failed to generate resolvers: " . $e->getMessage();
    exit(1);
}
```
