<?php

declare(strict_types=1);

use Laminas\ServiceManager\ServiceManager;
use Mezzio\Application;
use Mezzio\Container\ApplicationConfigInjectionDelegator;
use Mezzio\MiddlewareFactory;

return [
    'dependencies' => [
        'delegators' => [
            Application::class => [
                ApplicationConfigInjectionDelegator::class,
            ],
        ],
        'factories' => [
            MiddlewareFactory::class => function ($container) {
                return new MiddlewareFactory($container);
            },
        ],
    ],
    'mezzio' => [
        'programmatic_pipeline' => true,
        'middleware_pipeline' => [
            'routing' => [
                'middleware' => [
                    \GraphQL\Middleware\GraphQLMiddleware::class,
                ],
                'priority' => 1,
            ],
        ],
    ],
];
