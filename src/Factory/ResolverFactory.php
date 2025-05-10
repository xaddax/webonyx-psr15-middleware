<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Factory;

use GraphQL\Middleware\Contract\ResolverInterface;
use Psr\Container\ContainerInterface;

class ResolverFactory
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $resolverNamespace = 'Application\GraphQL\Resolver'
    ) {
    }

    public function createResolver(string $operationName): ?ResolverInterface
    {
        // Try operation-specific resolver first
        $resolverClass = sprintf(
            '%s\\%sResolver',
            $this->resolverNamespace,
            $operationName
        );

        if (class_exists($resolverClass)) {
            /** @var ResolverInterface */
            return $this->container->has($resolverClass)
                ? $this->container->get($resolverClass)
                : new $resolverClass();
        }

        return null;
    }
}
