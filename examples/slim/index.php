<?php
declare(strict_types=1);

use GraphQL\Server\ServerConfig;
use GraphQL\Type\Schema;
use Nyholm\Psr7\Factory\Psr17Factory;
use Slim\Factory\AppFactory;
use GraphQL\Middleware\Config\DefaultConfiguration;
use GraphQL\Middleware\Factory\DefaultResponseFactory;
use GraphQL\Middleware\Factory\GeneratedSchemaFactory;
use GraphQL\Middleware\Factory\GraphQLMiddlewareFactory;

require __DIR__ . '/../../vendor/autoload.php';

// Create PSR-17 factories
$psr17Factory = new Psr17Factory();

// Create Schema Configuration
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

// Create Schema
$schemaFactory = new GeneratedSchemaFactory($schemaConfig);
$schema = $schemaFactory->createSchema();

// Create Server Config
$serverConfig = new ServerConfig();
$serverConfig->setSchema($schema);

// Create GraphQL Middleware
$middlewareFactory = new GraphQLMiddlewareFactory(
    serverConfig: $serverConfig,
    responseFactory: $psr17Factory,
    streamFactory: $psr17Factory,
);

// Create Slim App
$app = AppFactory::create();

// Add GraphQL Middleware
$app->add($middlewareFactory->createMiddleware());

$app->run();
