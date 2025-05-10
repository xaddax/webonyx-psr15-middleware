<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Generator;

use GraphQL\Language\Parser;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Middleware\Contract\SchemaAnalyzerInterface;
use GraphQL\Middleware\Contract\TypeMapperInterface;
use GraphQL\Middleware\Exception\GeneratorException;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;

class AstSchemaAnalyzer implements SchemaAnalyzerInterface
{
    private DocumentNode $ast;

    public function __construct(
        private readonly Schema $schema,
        private readonly TypeMapperInterface $typeMapper
    ) {
        $sdl = SchemaPrinter::doPrint($this->schema);
        $this->ast = Parser::parse($sdl);

        if (!$this->ast instanceof DocumentNode) {
            throw new GeneratorException('Schema AST is not a DocumentNode');
        }
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
            if (
                !($definition instanceof ObjectTypeDefinitionNode
                    || $definition instanceof InterfaceTypeDefinitionNode)
            ) {
                continue;
            }

            if ($definition->fields->count() === 0) {
                continue;
            }

            $isRootType = in_array($definition->name->value, ['Query', 'Mutation']);

            foreach ($definition->fields as $field) {
                // Skip scalar fields in root types that are empty placeholders
                if ($isRootType && $field->name->value === '_empty') {
                    continue;
                }

                // For non-root types, only include fields that return non-scalar types
                if (!$isRootType) {
                    $type = $field->type;
                    while ($type instanceof NonNullTypeNode || $type instanceof ListTypeNode) {
                        $type = $type->type;
                    }
                    if ($this->isScalarType($type)) {
                        continue;
                    }
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
    private function getTypeString(mixed $typeNode, bool $isNonNull = false): string
    {
        // Handle non-null types first
        if ($typeNode instanceof NonNullTypeNode) {
            return $this->getTypeString($typeNode->type, true);
        }

        // Handle list types
        if ($typeNode instanceof ListTypeNode) {
            // For list types, we need to handle the inner type's nullability separately
            // The inner type should be nullable unless it's marked with !
            // Pass through the current isNonNull for the inner type
            $innerType = $this->getTypeString($typeNode->type, false);
            // The array itself is nullable unless the outer type is marked with !
            return $isNonNull ? 'array<' . $innerType . '>' : 'array<' . $innerType . '>|null';
        }

        // Handle named types - in GraphQL, ALL types are nullable by default unless marked with !
        if ($typeNode instanceof NamedTypeNode) {
            $baseType = $this->getBaseType($typeNode);
            // Add |null for any type that isn't marked as non-null
            return $isNonNull ? $baseType : $baseType . '|null';
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
        if ($typeNode instanceof NonNullTypeNode) {
            return $this->getBaseType($typeNode->type);
        }

        if ($typeNode instanceof ListTypeNode) {
            return $this->getBaseType($typeNode->type);
        }

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

    /**
     * @return array<string, array{
     *     name: string,
     *     fields: array<string, string>,
     *     description: string|null
     * }>
     */
    public function getRequestRequirements(): array
    {
        $requirements = [];
        foreach ($this->ast->definitions as $definition) {
            if (!($definition instanceof \GraphQL\Language\AST\InputObjectTypeDefinitionNode)) {
                continue;
            }

            $fields = [];
            foreach ($definition->fields as $field) {
                $fields[$field->name->value] = $this->getTypeString($field->type);
            }

            $requirements[$definition->name->value] = [
                'name' => $definition->name->value,
                'fields' => $fields,
                'description' => $definition->description?->value,
            ];
        }

        return $requirements;
    }

    /**
     * @return array<string, array{
     *     name: string,
     *     fields: array<string, string>,
     *     description: string|null
     * }>
     */
    public function getEntityRequirements(): array
    {
        $requirements = [];
        foreach ($this->ast->definitions as $definition) {
            if (!($definition instanceof ObjectTypeDefinitionNode)) {
                continue;
            }

            // Skip root operation types
            if (in_array($definition->name->value, ['Query', 'Mutation', 'Subscription'])) {
                continue;
            }

            $fields = [];
            foreach ($definition->fields as $field) {
                $fields[$field->name->value] = $this->getTypeString($field->type);
            }

            $requirements[$definition->name->value] = [
                'name' => $definition->name->value,
                'fields' => $fields,
                'description' => $definition->description?->value,
            ];
        }

        return $requirements;
    }
}
