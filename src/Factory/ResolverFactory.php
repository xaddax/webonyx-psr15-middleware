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
        private readonly string $resolverNamespace = 'App\GraphQL\Resolver'
    ) {
    }

    /**
     * @param string|ResolveInfo $operationName Either a string operation name or a ResolveInfo object
     */
    public function createResolver(string|ResolveInfo $operationName): ?ResolverInterface
    {
        if ($operationName instanceof ResolveInfo) {
            // Handle ResolveInfo object
            $resolverClass = sprintf(
                '%s\\%s\\%sResolver',
                $this->resolverNamespace,
                $this->getTypeName($operationName),
                $this->getOperationName($operationName),
            );
        } else {
            // Handle string operation name (for backward compatibility)
            $resolverClass = sprintf(
                '%s\\%sResolver',
                $this->resolverNamespace,
                $operationName
            );
        }

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
