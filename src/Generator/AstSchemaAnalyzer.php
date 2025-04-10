<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Generator;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Middleware\Contract\SchemaAnalyzerInterface;
use GraphQL\Middleware\Contract\TypeMapperInterface;
use GraphQL\Middleware\Exception\GeneratorException;

class AstSchemaAnalyzer implements SchemaAnalyzerInterface
{
    public function __construct(
        private readonly DocumentNode $ast,
        private readonly TypeMapperInterface $typeMapper
    ) {
    }

    /**
     * @return array<string, array{
     *     type: string,
     *     field: string,
     *     returnType: string,
     *     args: array<string, string>,
     *     description: string|null
     * }>
     */
    public function getResolverRequirements(): array
    {
        $requirements = [];
        foreach ($this->ast->definitions as $definition) {
            if (!$definition instanceof ObjectTypeDefinitionNode) {
                continue;
            }

            // Skip scalar and input types
            if (in_array($definition->name->value, ['String', 'Int', 'Float', 'Boolean', 'ID'])) {
                continue;
            }

            if ($definition->fields->count() === 0) {
                continue;
            }

            foreach ($definition->fields as $field) {
                // Skip fields that return scalar types
                if ($this->isScalarType($field->type)) {
                    continue;
                }

                $key = $definition->name->value . '.' . $field->name->value;
                $requirements[$key] = [
                    'args' => $field->arguments ? $this->getArgumentTypes($field->arguments) : [],
                    'description' => $field->description?->value,
                    'field' => $field->name->value,
                    'returnType' => $this->getTypeString($field->type),
                    'type' => $definition->name->value,
                ];
            }
        }
        return $requirements;
    }

    /**
     * @param ListTypeNode|NamedTypeNode|NonNullTypeNode $typeNode
     */
    private function getTypeString(mixed $typeNode): string
    {
        // Handle non-null and list types recursively
        if (property_exists($typeNode, 'type')) {
            $baseType = $this->getTypeString($typeNode->type);
            return $typeNode instanceof NonNullTypeNode
                ? $baseType
                : $baseType . '|null';
        }

        // Handle list types
        if ($typeNode instanceof ListTypeNode) {
            $innerType = $this->getTypeString($typeNode->type);
            return 'array<' . $innerType . '>';
        }

        // Get base type
        return $this->getBaseType($typeNode);
    }



    /**
     * @param NodeList<InputValueDefinitionNode> $args
     * @return array<string, string>
     */
    private function getArgumentTypes(NodeList $args): array
    {
        $types = [];
        foreach ($args as $arg) {
            $types[$arg->name->value] = $this->getTypeString($arg->type);
        }
        return $types;
    }

    /**
     * @param mixed $typeNode
     */
    private function getBaseType(mixed $typeNode): string
    {
        if (!$typeNode instanceof NamedTypeNode) {
            throw new GeneratorException('Invalid type node');
        }

        return $this->typeMapper->toPhpType($typeNode->name->value);
    }

    /**
     * Check if a type node represents a scalar type
     *
     * @param ListTypeNode|NamedTypeNode|NonNullTypeNode $typeNode
     */
    private function isScalarType(mixed $typeNode): bool
    {
        // For non-null types, check the inner type
        if ($typeNode instanceof NonNullTypeNode) {
            return $this->isScalarType($typeNode->type);
        }

        // For list types, check the inner type
        if ($typeNode instanceof ListTypeNode) {
            return $this->isScalarType($typeNode->type);
        }

        // For named types, check if it's a built-in scalar
        if ($typeNode instanceof NamedTypeNode) {
            // Check if it's a built-in scalar type
            if (in_array($typeNode->name->value, ['String', 'Int', 'Float', 'Boolean', 'ID'])) {
                return true;
            }

            // Check if it's a custom scalar type
            foreach ($this->ast->definitions as $definition) {
                if (
                    $definition instanceof ScalarTypeDefinitionNode &&
                    $definition->name->value === $typeNode->name->value
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
