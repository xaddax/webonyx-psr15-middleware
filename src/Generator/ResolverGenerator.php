<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Generator;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Middleware\Contract\SchemaAnalyzerInterface;
use GraphQL\Middleware\Contract\TemplateEngineInterface;
use GraphQL\Middleware\Contract\TypeMapperInterface;
use GraphQL\Middleware\Exception\GeneratorException;
use GraphQL\Middleware\Factory\GeneratedSchemaFactory;
use GraphQL\Middleware\Generator\DefaultTypeMapper;
use GraphQL\Middleware\Config\GeneratorConfig;

class ResolverGenerator
{
    private string $templatePath;

    public function __construct(
        private readonly AstSchemaAnalyzer $schemaAnalyzer,
        private readonly GeneratorConfig $config,
        private readonly TemplateEngineInterface $templateEngine,
    ) {
        $resolverConfig = $config->getResolverConfig();
        $this->templatePath = $resolverConfig->getTemplatePath();
        if (!file_exists($this->templatePath)) {
            throw new GeneratorException('Template file not found: ' . $this->templatePath);
        }
    }

    /**
     * Generate all required resolvers based on the schema
     *
     * @throws GeneratorException If schema analysis or generation fails
     */
    public function generateAll(): void
    {
        $requirements = $this->schemaAnalyzer->getResolverRequirements();

        if (empty($requirements)) {
            throw new GeneratorException('No resolver requirements found in schema');
        }

        foreach ($requirements as $requirement) {
            $this->generateResolver($requirement);
        }
    }

    protected function generateResolver(array $requirement): void
    {
        $resolverConfig = $this->config->getResolverConfig();
        $typeDir = $resolverConfig->getFileLocation() . '/' . ucfirst($requirement['type']);
        $className = ucfirst($requirement['field']) . 'Resolver';
        $filePath = $typeDir . '/' . $className . '.php';

        // Skip if file already exists
        if (file_exists($filePath)) {
            return;
        }

        $template = file_get_contents($this->templatePath);
        if ($template === false) {
            throw new GeneratorException('Failed to read template file: ' . $this->templatePath);
        }

        // Handle return type imports
        $imports = [];
        $returnType = $requirement['returnType'];
        if (!in_array($returnType, ['string', 'int', 'float', 'bool', 'array'])) {
            $baseType = str_replace('|null', '', $returnType);
            $imports[] = "use App\\GraphQL\\Type\\{$baseType};";
        }

        $content = $this->templateEngine->render(
            $template,
            [
                'namespace' => $resolverConfig->getNamespace() . '\\' . ucfirst($requirement['type']),
                'className' => $className,
                'description' => $this->formatDescription($requirement),
                'returnType' => $returnType,
                'imports' => implode("\n", $imports),
            ]
        );

        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true)) {
                throw new GeneratorException('Failed to create directory: ' . $dir);
            }
        }

        if (@file_put_contents($filePath, $content) === false) {
            throw new GeneratorException('Failed to write resolver file: ' . $filePath);
        }
    }

    private function formatDescription(array $requirement): string
    {
        $description = "/**\n * Resolver for {$requirement['type']}.{$requirement['field']}\n";

        if ($requirement['description']) {
            $description .= " *\n * " . $requirement['description'] . "\n";
        }

        if ($requirement['args']) {
            $description .= " *\n * @param array \$args\n";
            foreach ($requirement['args'] as $name => $type) {
                // Handle nullable types
                if (str_ends_with($type, '|null')) {
                    $description .= " *   - $name: $type\n";
                } else {
                    $description .= " *   - $name: $type\n";
                }
            }
        }

        $description .= " */";
        return $description;
    }
}
