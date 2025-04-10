<?php

declare(strict_types=1);

use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Server\ServerConfig;
use GraphQL\Middleware\Factory\DefaultResponseFactory;
use GraphQL\Middleware\GraphQLMiddleware;

return [
    'dependencies' => [
        'factories' => [
            Schema::class => function () {
                $queryType = new ObjectType([
                    'name' => 'Query',
                    'fields' => [
                        'hello' => [
                            'type' => Type::string(),
                            'resolve' => fn () => 'Hello World!'
                        ],
                        'echo' => [
                            'type' => Type::string(),
                            'args' => [
                                'message' => Type::nonNull(Type::string())
                            ],
                            'resolve' => fn ($root, array $args) => $args['message']
                        ]
                    ]
                ]);

                $mutationType = new ObjectType([
                    'name' => 'Mutation',
                    'fields' => [
                        'greet' => [
                            'type' => Type::string(),
                            'args' => [
                                'name' => Type::nonNull(Type::string())
                            ],
                            'resolve' => fn ($root, array $args) => "Hello, {$args['name']}!"
                        ]
                    ]
                ]);

                return new Schema([
                    'query' => $queryType,
                    'mutation' => $mutationType
                ]);
            },
            ServerConfig::class => function ($container) {
                $config = new ServerConfig();
                $config->setSchema($container->get(Schema::class));
                return $config;
            },
            GraphQLMiddleware::class => function ($container) {
                return new GraphQLMiddleware(
                    $container->get(ServerConfig::class),
                    new DefaultResponseFactory(),
                    ['application/json']
                );
            }
        ]
    ]
];
