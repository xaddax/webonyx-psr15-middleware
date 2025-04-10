<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Resolver;

use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Middleware\Factory\ResolverFactory;
use GraphQL\Type\Definition\ResolveInfo;

class ResolverManager
{
    public function __construct(
        private readonly ResolverFactory $resolverFactory,
        private readonly mixed $fallbackResolver = null
    ) {
    }

    /**
     * Creates a type config decorator for schema building
     */
    public function createTypeConfigDecorator(): callable
    {
        return function (array $typeConfig, TypeDefinitionNode $typeDefinitionNode): array {
            if ($typeConfig['name'] === 'Query' || $typeConfig['name'] === 'Mutation') {
                $typeConfig['resolveField'] = function ($source, $args, $context, ResolveInfo $info) {
                    // Convert field name to operation name (e.g., getUser, createUser)
                    $operationName = $this->formatOperationName($info->fieldName);

                    // Try to get operation-specific resolver
                    $resolver = $this->resolverFactory->createResolver($operationName);

                    if ($resolver) {
                        return $resolver($source, $args, $context, $info);
                    }

                    // Fall back to default resolver if provided
                    if ($this->fallbackResolver) {
                        return ($this->fallbackResolver)($source, $args, $context, $info);
                    }

                    // Use webonyx default resolver as last resort
                    return null;
                };
            }

            return $typeConfig;
        };
    }

    private function formatOperationName(string $fieldName): string
    {
        // Convert camelCase to PascalCase
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $fieldName)));
    }
}
