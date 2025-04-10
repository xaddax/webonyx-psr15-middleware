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
    private TemplateEngineInterface $templateEngine;

    private const DEFAULT_TEMPLATE = <<<'TEMPLATE'
<?php

declare(strict_types=1);

namespace {{namespace}};

use GraphQL\Middleware\Contract\ResolverInterface;

{{description}}
class {{className}} implements ResolverInterface
{
    public function __invoke($source, array $args, $context, $info): {{returnType}}
    {
        // TODO: Implement resolver
        throw new \RuntimeException('Not implemented');
    }
}
TEMPLATE;

    public function __construct(
        private readonly GeneratedSchemaFactory $schemaFactory,
        private readonly string $namespace,
        private readonly string $outputDirectory,
        ?TemplateEngineInterface $templateEngine = null
    ) {
        $this->templateEngine = $templateEngine ?? new SimpleTemplateEngine();
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

        $content = $this->templateEngine->render(
            self::DEFAULT_TEMPLATE,
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
