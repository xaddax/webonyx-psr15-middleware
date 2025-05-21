<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Schema;

use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\Printer;

class SchemaMerger
{
    /**
     * @param string[] $files
     * @return Source
     */
    public function merge(array $files): Source
    {
        if (empty($files)) {
            throw new \InvalidArgumentException('No schema files provided.');
        }

        $typeDefs = [];
        $scalarDefs = [];
        $inputDefs = [];
        $operationTypes = [
            'Query' => [],
            'Mutation' => [],
            'Subscription' => [],
        ];
        $inputTypeMap = [];
        $astDocs = [];

        foreach ($files as $file) {
            if (!file_exists($file)) {
                throw new \RuntimeException("Schema file not found: $file");
            }
            $content = file_get_contents($file);
            if ($content === false) {
                throw new \RuntimeException("Failed to read schema file: $file");
            }
            try {
                $astDocs[] = Parser::parse($content);
            } catch (\Throwable $e) {
                $this->logAndThrow("Invalid GraphQL in file $file: " . $e->getMessage());
            }
        }

        foreach ($astDocs as $doc) {
            foreach ($doc->definitions as $def) {
                switch ($def->kind) {
                    case NodeKind::SCALAR_TYPE_DEFINITION:
                        if (
                            property_exists($def, 'name')
                            && is_object($def->name)
                            && property_exists($def->name, 'value')
                        ) {
                            $name = $def->name->value;
                        } else {
                            break;
                        }
                        if (isset($scalarDefs[$name]) && !$this->nodesAreEqual($scalarDefs[$name], $def)) {
                            $this->logAndThrow("Conflicting scalar definition for $name");
                        }
                        $scalarDefs[$name] = $def;
                        break;
                    case NodeKind::INPUT_OBJECT_TYPE_DEFINITION:
                        if (
                            property_exists($def, 'name')
                            && is_object($def->name)
                            && property_exists($def->name, 'value')
                        ) {
                            $name = $def->name->value;
                        } else {
                            break;
                        }
                        if (isset($inputDefs[$name]) && !$this->nodesAreEqual($inputDefs[$name], $def)) {
                            $this->logAndThrow("Conflicting input definition for $name");
                        }
                        $inputDefs[$name] = $def;
                        $inputTypeMap[$name] = $def;
                        break;
                    case NodeKind::OBJECT_TYPE_DEFINITION:
                        if (
                            property_exists($def, 'name')
                            && is_object($def->name)
                            && property_exists($def->name, 'value')
                        ) {
                            $name = $def->name->value;
                        } else {
                            break;
                        }
                        if (isset($operationTypes[$name])) {
                            // Merge root operation types
                            if (property_exists($def, 'fields') && $def->fields instanceof NodeList) {
                                foreach ($def->fields as $field) {
                                    if (
                                        !property_exists($field, 'name')
                                        || !is_object($field->name)
                                        || !property_exists($field->name, 'value')
                                    ) {
                                        continue;
                                    }
                                    $fieldName = $field->name->value;
                                    if (
                                        isset($operationTypes[$name][$fieldName]) &&
                                        !$this->nodesAreEqual($operationTypes[$name][$fieldName], $field)
                                    ) {
                                        $this->logAndThrow(
                                            "Conflicting field '$fieldName' in root operation type $name"
                                        );
                                    }
                                    $operationTypes[$name][$fieldName] = $field;
                                }
                            }
                        } else {
                            if (isset($typeDefs[$name]) && !$this->nodesAreEqual($typeDefs[$name], $def)) {
                                $this->logAndThrow("Conflicting type definition for $name");
                            }
                            $typeDefs[$name] = $def;
                        }
                        break;
                }
            }
        }

        // Build merged DocumentNode
        $mergedDefinitions = [];
        foreach ($scalarDefs as $def) {
            $mergedDefinitions[] = $def;
        }
        foreach ($typeDefs as $def) {
            $mergedDefinitions[] = $def;
        }
        foreach ($inputDefs as $def) {
            $mergedDefinitions[] = $def;
        }
        foreach (['Query', 'Mutation', 'Subscription'] as $opType) {
            if (!empty($operationTypes[$opType])) {
                $fields = array_values(iterator_to_array($operationTypes[$opType]));
                $typeNode = new ObjectTypeDefinitionNode([
                    'name' => new NameNode(['value' => $opType]),
                    'fields' => new NodeList($fields),
                    'interfaces' => new NodeList([]),
                    'directives' => new NodeList([]),
                    'description' => null,
                ]);
                $mergedDefinitions[] = $typeNode;
            }
        }
        $mergedDoc = new DocumentNode([
            'definitions' => new NodeList($mergedDefinitions),
        ]);
        $schema = Printer::doPrint($mergedDoc);
        return new Source($schema);
    }

    /**
     * @param object $a
     * @param object $b
     * @return bool
     */
    private function nodesAreEqual(object $a, object $b): bool
    {
        // Compare nodes by their array representation
        return $this->nodeToArray($a) === $this->nodeToArray($b);
    }

    /**
     * @param mixed $node
     * @return mixed
     */
    private function nodeToArray($node)
    {
        // Handle NodeList explicitly
        if ($node instanceof \GraphQL\Language\AST\NodeList) {
            $result = [];
            foreach ($node as $item) {
                $result[] = $this->nodeToArray($item);
            }
            return $result;
        }
        if (is_array($node)) {
            $result = [];
            foreach ($node as $k => $v) {
                $result[$k] = $this->nodeToArray($v);
            }
            return $result;
        } elseif (is_object($node)) {
            $result = [];
            foreach (get_object_vars($node) as $k => $v) {
                if ($k === 'loc') {
                    continue; // skip location info
                }
                $result[$k] = $this->nodeToArray($v);
            }
            return $result;
        }
        // Allow scalars (string, int, etc.)
        return $node;
    }

    private function logAndThrow(string $message): void
    {
        fwrite(STDERR, $message . "\n");
        echo $message . "\n";
        throw new \RuntimeException($message);
    }
}
