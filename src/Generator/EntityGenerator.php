<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Generator;

use GraphQL\Middleware\Contract\TemplateEngineInterface;
use GraphQL\Middleware\Exception\GeneratorException;
use GraphQL\Middleware\Config\GeneratorConfig;
use GraphQL\Middleware\Domain\BaseEntity;

class EntityGenerator
{
    private string $templatePath;

    public function __construct(
        private readonly AstSchemaAnalyzer $schemaAnalyzer,
        private readonly GeneratorConfig $config,
        private readonly TemplateEngineInterface $templateEngine,
    ) {
        $entityConfig = $config->getEntityConfig();
        $this->templatePath = $entityConfig->getTemplatePath();
        if (!file_exists($this->templatePath)) {
            throw new GeneratorException('Template file not found: ' . $this->templatePath);
        }
    }

    /**
     * Generate all required entities based on the schema
     *
     * @throws GeneratorException If schema analysis or generation fails
     */
    public function generateAll(): void
    {
        $requirements = $this->schemaAnalyzer->getEntityRequirements();

        if (empty($requirements)) {
            throw new GeneratorException('No entity requirements found in schema');
        }

        foreach ($requirements as $requirement) {
            $this->generateEntity($requirement);
        }
    }

    protected function generateEntity(array $requirement): void
    {
        $entityConfig = $this->config->getEntityConfig();
        $className = $requirement['name'];
        $filePath = $entityConfig->getFileLocation() . '/' . $className . '.php';

        // Skip if file already exists
        if (@file_exists($filePath)) {
            return;
        }

        // Read template file
        $template = @file_get_contents($this->templatePath);
        if ($template === false) {
            throw new GeneratorException('Failed to read template file: ' . $this->templatePath);
        }

        $content = $this->templateEngine->render(
            $template,
            [
                'namespace' => $entityConfig->getNamespace(),
                'className' => $className,
                'description' => $requirement['description'],
                'fields' => $requirement['fields'],
            ]
        );

        $dir = dirname($filePath);

        // Try to create directory if it doesn't exist
        if (!@is_dir($dir)) {
            if (!@mkdir($dir, 0777, true)) {
                throw new GeneratorException('Failed to create directory: ' . $dir);
            }
        }

        // Try to write the file
        if (@file_put_contents($filePath, $content) === false) {
            throw new GeneratorException('Failed to write entity file: ' . $filePath);
        }
    }

    protected function generateEntityContent(string $className, array $fields): string
    {
        $entityConfig = $this->config->getEntityConfig();
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$entityConfig->getNamespace()};

use GraphQL\Middleware\Domain\BaseEntity;

class {$className} extends BaseEntity
{
    public function __construct(
        private readonly array \$data = []
    ) {}

    public function toArray(): array
    {
        return \$this->data;
    }
}
PHP;
    }

    protected function hasCustomMethods(string $className): bool
    {
        // Check if class has any methods beyond those defined in BaseEntity
        $baseMethods = get_class_methods(BaseEntity::class);
        $classMethods = get_class_methods($className);

        return !empty(array_diff($classMethods, $baseMethods));
    }
}
