<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Resolver;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Middleware\Factory\ResolverFactory;
use GraphQL\Type\Definition\ResolveInfo;

class ResolverManager
{
    public function __construct(
        private readonly ResolverFactory $resolverFactory,
        /** @var callable|null */
        private readonly mixed $fallbackResolver = null,
    ) {}

    /**
     * Creates a type config decorator for schema building
     */
    public function createTypeConfigDecorator(): callable
    {
        return function (array $typeConfig, TypeDefinitionNode $typeDefinitionNode): array {
            if ($typeConfig['name'] === 'Query' || $typeConfig['name'] === 'Mutation') {
                $typeConfig['resolveField'] = function ($source, $args, $context, ResolveInfo $info) {
                    $operationName = $this->formatOperationName($info->fieldName);

                    /** @var mixed $resolver */
                    $resolver = $this->resolverFactory->createResolver($operationName);

                    if ($resolver !== null) {
                        if (!is_callable($resolver)) {
                            throw new \RuntimeException("Resolver for {$operationName} is not callable");
                        }
                        return call_user_func($resolver, $source, $args, $context, $info);
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

    public function createFieldConfigDecorator(): callable
    {
        return function (array $fieldConfig, FieldDefinitionNode $fieldDefinitionNode, ObjectTypeDefinitionNode $node): array {
            $fieldConfig['resolve'] = function ($source, $args, $context, ResolveInfo $info) use ($fieldDefinitionNode, $node) {
                
                /** @var mixed $resolver */
                $resolver = $this->resolverFactory->createResolver($info);
                
                if ($resolver !== null) {
                    if (!is_callable($resolver)) {
                        throw new \RuntimeException("Resolver for {$info->fieldName} is not callable");
                    }
                    return call_user_func($resolver, $source, $args, $context, $info);
                }
                
                // Fall back to default resolver if provided
                if ($this->fallbackResolver) {
                    return ($this->fallbackResolver)($source, $args, $context, $info);
                }
                
                // Use webonyx default resolver as last resort
                return null;
            };

            return $fieldConfig;
        };
    }
}
