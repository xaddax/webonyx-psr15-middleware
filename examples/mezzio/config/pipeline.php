<?php

declare(strict_types=1);

use Laminas\ServiceManager\ServiceManager;
use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    foreach ($container->get('config')['mezzio']['middleware_pipeline'] as $name => $spec) {
        if (isset($spec['middleware'])) {
            foreach ((array) $spec['middleware'] as $middleware) {
                $app->pipe($factory->prepare($middleware));
            }
        }
    }
};
