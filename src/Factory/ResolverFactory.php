<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Factory;

use GraphQL\Middleware\Contract\ResolverInterface;
use GraphQL\Type\Definition\ResolveInfo;
use Psr\Container\ContainerInterface;

class ResolverFactory
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $resolverNamespace = 'Application\GraphQL\Resolver'
    ) {}

    public function createResolver(ResolveInfo $resolveInfo): ?ResolverInterface
    {
        // Try operation-specific resolver first
        $resolverClass = sprintf(
            '%s\\%s\\%sResolver',
            $this->resolverNamespace,
            $this->getTypeName($resolveInfo),
            $this->getOperationName($resolveInfo),
        );

        if (class_exists($resolverClass)) {
            /** @var ResolverInterface */
            return $this->container->has($resolverClass)
                ? $this->container->get($resolverClass)
                : new $resolverClass();
        }

        return null;
    }

    private function getOperationName(ResolveInfo $resolveInfo): string
    {
        // Convert camelCase to PascalCase
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $resolveInfo->fieldName)));
    }

    private function getTypeName(ResolveInfo $resolveInfo): string
    {
        return $resolveInfo->parentType->name;
    }
}
