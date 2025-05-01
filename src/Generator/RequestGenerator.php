<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Generator;

use GraphQL\Middleware\Contract\SchemaAnalyzerInterface;
use GraphQL\Middleware\Contract\TemplateEngineInterface;
use GraphQL\Middleware\Exception\GeneratorException;
use GraphQL\Middleware\Config\GeneratorConfig;

class RequestGenerator
{
    private string $templatePath;

    public function __construct(
        private readonly AstSchemaAnalyzer $schemaAnalyzer,
        private readonly GeneratorConfig $config,
        private readonly TemplateEngineInterface $templateEngine,
    ) {
        $requestConfig = $config->getRequestConfig();
        $this->templatePath = $requestConfig->getTemplatePath();
        if (!file_exists($this->templatePath)) {
            throw new GeneratorException('Template file not found: ' . $this->templatePath);
        }
    }

    /**
     * Generate all required requests based on the schema
     *
     * @throws GeneratorException If schema analysis or generation fails
     */
    public function generateAll(): void
    {
        $requirements = $this->schemaAnalyzer->getRequestRequirements();

        if (empty($requirements)) {
            throw new GeneratorException('No request requirements found in schema');
        }

        foreach ($requirements as $requirement) {
            $this->generateRequest($requirement);
        }
    }

    protected function generateRequest(array $requirement): void
    {
        $requestConfig = $this->config->getRequestConfig();
        $className = $requirement['name'];
        $filePath = $requestConfig->getFileLocation() . '/' . $className . '.php';

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
                'namespace' => $requestConfig->getNamespace(),
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
            throw new GeneratorException('Failed to write request file: ' . $filePath);
        }
    }
}
