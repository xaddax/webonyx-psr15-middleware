# PSR-15 GraphQL Middleware

A framework-agnostic PSR-15 middleware for handling GraphQL requests using webonyx/graphql-php.

[![codecov](https://codecov.io/gh/xaddax/webonyx-psr15-middleware/graph/badge.svg)](https://codecov.io/gh/xaddax/webonyx-psr15-middleware)

## Features

- PSR-15 compliant middleware
- Framework agnostic
- Schema caching support
- Request preprocessing capabilities
- Flexible response handling

## Installation

```bash
composer require xaddax/webonyx-psr15-middleware
```

## Requirements

- PHP 8.3+
- PSR-7 implementation (e.g., nyholm/psr7)
- PSR-17 HTTP factories implementation
- PSR-6 cache implementation (optional, e.g., symfony/cache)

## Basic Usage

```php
use GraphQL\Server\ServerConfig;
use GraphQL\Type\Schema;
use Nyholm\Psr7\Factory\Psr17Factory;
use GraphQL\Middleware\Factory\GraphQLMiddlewareFactory;

// Create PSR-17 factories
$psr17Factory = new Psr17Factory();

// Configure your GraphQL schema and server
$serverConfig = new ServerConfig();
$serverConfig->setSchema($schema);

// Create the middleware factory
$middlewareFactory = new GraphQLMiddlewareFactory(
    serverConfig: $serverConfig,
    responseFactory: $psr17Factory,
    streamFactory: $psr17Factory
);

// Create and add the middleware to your application
$middleware = $middlewareFactory->createMiddleware();
```

## Schema Generation

The library includes support for generating schemas from .graphql files:

```php
use GraphQL\Middleware\Config\DefaultConfiguration;
use GraphQL\Middleware\Factory\GeneratedSchemaFactory;

// Configure schema generation
$schemaConfig = new DefaultConfiguration([
    'schema' => [
        'directories' => [__DIR__ . '/schema'],
        'parser_options' => [],
    ],
    'cache' => [
        'enabled' => true,
        'directory' => __DIR__ . '/cache',
    ],
]);

// Create schema factory
$schemaFactory = new GeneratedSchemaFactory($schemaConfig);
$schema = $schemaFactory->createSchema();
```

## Framework Integration

### Slim 4

```php
use Slim\Factory\AppFactory;

$app = AppFactory::create();
$app->add($middlewareFactory->createMiddleware());
```

### Mezzio

```php
use Mezzio\Application;

$app = new Application();
$app->pipe($middlewareFactory->createMiddleware());
```

### Laravel

```php
use Illuminate\Support\ServiceProvider;

class GraphQLServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $middleware = $this->app->make(GraphQLMiddlewareFactory::class)
            ->createMiddleware();
            
        $this->app['router']->middleware('graphql', $middleware);
    }
}
```

## Request Preprocessing

You can add authentication/authorization by implementing the `RequestPreprocessorInterface`:

```php
use GraphQL\Middleware\RequestPreprocessorInterface;

class AuthPreprocessor implements RequestPreprocessorInterface
{
    public function process(ServerRequestInterface $request): ServerRequestInterface
    {
        $token = $request->getHeaderLine('Authorization');
        if (!$this->isValidToken($token)) {
            throw new UnauthorizedException('Invalid token');
        }
        
        return $request;
    }
}

// Add to middleware factory
$middlewareFactory = new GraphQLMiddlewareFactory(
    serverConfig: $serverConfig,
    responseFactory: $psr17Factory,
    streamFactory: $psr17Factory,
    requestPreprocessor: new AuthPreprocessor()
);
```

## Development

### Code Quality

The project uses PHP_CodeSniffer for coding standards and PHPStan for static analysis.

To check coding standards:
```bash
composer cs-check
```

To automatically fix coding standards:
```bash
composer cs-fix
```

To run static analysis:
```bash
composer stan
```

To run all checks (coding standards, static analysis, and tests):
```bash
composer check
```

### Testing

To run the test suite:

```bash
composer test
```

## Examples

Check the `examples/` directory for complete working examples with different frameworks:

- `examples/slim/` - Slim 4 integration
- `examples/mezzio/` - Mezzio integration
- `examples/laravel/` - Laravel integration

## License

MIT License. See LICENSE file for details.

==========================

To use the middleware in Laminas Mezzio, configure the factories

in `config/autoload/dependencies.global.php`

```php
return [
    'dependencies' => [
        'factories'  => [
            \GraphQL\Server\StandardServer::class => \Xaddax\GraphQL\Factory\StandardServerFactory::class,
            \GraphQL\Middleware\GraphQLMiddleware::class => \Xaddax\GraphQL\Factory\GraphQLMiddlewareFactory::class,
        ],
    ],
];
```

Add configuration in `config/autoload/graphql.global.php`

```php
return [
    'graphQL' => [
        'middleware' => [
            'allowedHeaders' => [
                'application/graphql',
                'application/json',
            ],
        ],
        'schema' => \Path\To\Schema::class, // optional, defaults to webonyx Schema
        'schemaConfig' => [], // optional, if not configured expected in Schema class constructor
        'server' => \Path\To\Server::class, // not yet implemented, defaults to webonyx StandardServer
        'serverConfig' => [
            'context' => \GraphQL\Context\TokenContext::class
            'schema' => \Path\To\Your\Schema::class, 
        ],
    ],
];
```

see the [WebOnyx Server Configuration Documentation](https://webonyx.github.io/graphql-php/executing-queries/#server-configuration-options) for the full options for 
the server config.

You'll need to set up the route. In `config/routes.php`
```php
return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container) : void {
    $app->post('/graphql', \GraphQL\Middleware\GraphQLMiddleware::class, 'graphql');
};
```

Schema Definition Language
--------------------------
You can also use a Schema Definition Language as discussed 
[in the webonyx documentation](https://webonyx.github.io/graphql-php/schema-definition-language/).

In `config/autoload/graphql.global.php` change the schema in the `serverConfig` to `generatedSchema`
```php
return [
    'graphQL' => [
        'serverConfig' => [
            'schema' => 'generatedSchema',
        ],
    ],
];
```
Then inside of the `graphQL` config add the `generatedSchema` configuration
```php
return [
    'graphQL' => [
        'generatedSchema' => [
            'parserOptions' => [
                'experimentalFragmentVariables' => true, // to parse fragments
                'noLocation' => false, // default, set true for development
            ],
            'cache' => [
                'alwaysEnabled' => false, // default, set to true to cache when the system cache is not enabled
                'directoryChangeFilename' => 'directory-change-cache.php', // default
                'schemaCacheFilename' => 'schema-cache.php', // default 
            ],
            'schemaDirectories' => [
                '/full/path/to/schema-directory-1',
                '/full/path/to/schema-directory-2',
            ],
        ],
    ],
];
```
See [the documentation](https://webonyx.github.io/graphql-php/class-reference/#graphqllanguageparser) for
`parserOptions`

The cached data is stored in `data/cache/graphql`.

