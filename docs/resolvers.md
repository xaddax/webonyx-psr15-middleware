# GraphQL Resolvers

The middleware provides a flexible resolver system that allows you to implement operation-specific resolvers while maintaining compatibility with default resolution strategies.

## Configuration

Configure resolvers in your configuration array:

```php
$config = [
    'resolver' => [
        // Namespace for resolver classes
        'namespace' => 'App\\GraphQL\\Resolver',
        
        // Optional fallback resolver
        'fallback_resolver' => function($source, $args, $context, $info) {
            // Default resolution logic
            return $source[$info->fieldName] ?? null;
        }
    ]
];
```

## Creating Resolvers

1. Create a resolver class that implements `ResolverInterface`:

```php
namespace App\GraphQL\Resolver;

use GraphQL\Middleware\Contract\ResolverInterface;
use GraphQL\Type\Definition\ResolveInfo;

class GetUserResolver implements ResolverInterface
{
    public function __invoke($source, array $args, $context, ResolveInfo $info): mixed
    {
        // Implement resolver logic
        return [
            'id' => $args['id'],
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];
    }
}
```

2. The resolver class name should match the operation name in PascalCase with "Resolver" suffix:
   - `query { getUser }` → `GetUserResolver`
   - `mutation { createUser }` → `CreateUserResolver`

## Dependency Injection

Resolvers support dependency injection through PSR-11 container:

```php
namespace App\GraphQL\Resolver;

use App\Service\UserService;
use GraphQL\Middleware\Contract\ResolverInterface;
use GraphQL\Type\Definition\ResolveInfo;

class GetUserResolver implements ResolverInterface
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function __invoke($source, array $args, $context, ResolveInfo $info): mixed
    {
        return $this->userService->getUser($args['id']);
    }
}
```

## Resolution Strategy

The resolver system follows this resolution strategy:

1. Look for an operation-specific resolver class in the configured namespace
2. If found, instantiate through PSR-11 container if available
3. If no specific resolver is found, use the fallback resolver if configured
4. If no fallback resolver, use the default webonyx/graphql-php field resolver

## API Reference

### ResolverInterface

```php
interface ResolverInterface
{
    /**
     * Resolve a GraphQL field
     *
     * @param mixed $source The parent value
     * @param array $args Field arguments
     * @param mixed $context Request context
     * @param ResolveInfo $info Field resolution information
     * @return mixed The resolved value
     */
    public function __invoke($source, array $args, $context, ResolveInfo $info): mixed;
}
```

### ResolverFactory

```php
class ResolverFactory
{
    /**
     * @param ContainerInterface $container PSR-11 container
     * @param string $resolverNamespace Namespace for resolver classes
     */
    public function __construct(
        ContainerInterface $container,
        string $resolverNamespace = 'App\\GraphQL\\Resolver'
    );

    /**
     * Create a resolver instance for an operation
     *
     * @param string $operationName Name of the GraphQL operation
     * @return ResolverInterface|null Resolver instance or null if not found
     */
    public function createResolver(string $operationName): ?ResolverInterface;
}
```

### ResolverManager

```php
class ResolverManager
{
    /**
     * @param ResolverFactory $resolverFactory Factory for creating resolvers
     * @param callable|null $fallbackResolver Optional fallback resolver
     */
    public function __construct(
        ResolverFactory $resolverFactory,
        ?callable $fallbackResolver = null
    );

    /**
     * Create a type config decorator for schema building
     *
     * @return callable Type config decorator function
     */
    public function createTypeConfigDecorator(): callable;
}
```
