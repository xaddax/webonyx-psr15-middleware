<?php

declare(strict_types=1);

use Laminas\ServiceManager\ServiceManager;
use Mezzio\Application;
use Mezzio\MiddlewareFactory;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

$config = require 'config/config.php';

$container = new ServiceManager();
(function () use ($container, $config): void {
    foreach ($config['dependencies']['factories'] ?? [] as $name => $factory) {
        $container->setFactory($name, $factory);
    }
})->call($container);

$app = $container->get(Application::class);
$factory = $container->get(MiddlewareFactory::class);

(require 'config/pipeline.php')($app, $factory, $container);

$app->run();
