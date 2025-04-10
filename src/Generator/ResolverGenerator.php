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

class ResolverGenerator
{
    private const DEFAULT_TEMPLATE_PATH = '/templates/resolver.php.template';

    private string $templatePath;

    public function __construct(
        private readonly GeneratedSchemaFactory $schemaFactory,
        private readonly string $outputDirectory,
        private readonly string $namespace,
        private readonly TemplateEngineInterface $templateEngine,
        ?string $templatePath = null,
    ) {
        $this->templatePath = $templatePath ?? dirname(__DIR__, 2) . self::DEFAULT_TEMPLATE_PATH;
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
        try {
            $schema = $this->schemaFactory->createSchema();
        } catch (\Throwable $e) {
            throw new GeneratorException('Failed to create schema', 0, $e);
        }

        try {
            $schemaString = SchemaPrinter::doPrint($schema);
        } catch (\Throwable $e) {
            throw new GeneratorException('Failed to print schema', 0, $e);
        }

        try {
            $document = Parser::parse($schemaString);
        } catch (\Throwable $e) {
            throw new GeneratorException('Failed to parse schema', 0, $e);
        }

        $analyzer = new AstSchemaAnalyzer($document, new DefaultTypeMapper());
        $requirements = $analyzer->getResolverRequirements();

        if (empty($requirements)) {
            throw new GeneratorException('No resolver requirements found in schema');
        }

        foreach ($requirements as $requirement) {
            $this->generateResolver($requirement);
        }
    }

    protected function generateResolver(array $requirement): void
    {
        $typeDir = $this->outputDirectory . '/' . ucfirst($requirement['type']);
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

        $content = $this->templateEngine->render(
            $template,
            [
                'namespace' => $this->namespace . '\\' . ucfirst($requirement['type']),
                'className' => $className,
                'description' => $this->formatDescription($requirement),
                'returnType' => $requirement['returnType'] . '|null',
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
                $description .= " *   - $name: $type\n";
            }
        }

        $description .= " */";
        return $description;
    }
}
