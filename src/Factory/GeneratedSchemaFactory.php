<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Factory;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Middleware\Config\SchemaConfig;
use GraphQL\Middleware\Schema\SchemaMerger;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\AST;
use Psr\Container\ContainerInterface;

class GeneratedSchemaFactory
{
    private string $directoryChangeCacheFile;
    private SchemaConfig $config;
    private string $schemaCacheFile;
    private array $schemaFiles = [];

    public function __invoke(ContainerInterface $container): Schema
    {
        /** @var SchemaConfig $config */
        $config = $container->get(SchemaConfig::class);
        $this->config = $config;

        $this->setFilesFromConfig($config);

        $source = $this->getSourceAST();
        if (!$source instanceof DocumentNode) {
            throw new \RuntimeException('Invalid source type');
        }
        return BuildSchema::build(
            $source,
            $this->config->getTypeConfigDecorator(),
            $this->config->getSchemaOptions(),
            $this->config->getFieldConfigDecorator(),
        );
    }

    private function isCacheValid(): bool
    {
        return
            $this->config->isCacheEnabled() &&
            !$this->isDirectoryChangeDetected() &&
            file_exists($this->schemaCacheFile);
    }

    private function isDirectoryChangeDetected(): bool
    {
        $currentFiles = $this->schemaFiles;
        $previousFiles = $this->readDirectoryChangeCache();

        return $currentFiles !== $previousFiles;
    }

    private function readDirectoryChangeCache(): array
    {
        if (file_exists($this->directoryChangeCacheFile)) {
            return require $this->directoryChangeCacheFile;
        }

        return [];
    }

    private function buildSourceAST(): DocumentNode
    {
        $source = $this->parseGraphQLFiles();

        return Parser::parse($source, $this->config->getParserOptions());
    }

    private function getSourceAST(): Node
    {
        // the directories need to be scanned for both cache checking
        // and source building, so do it before anything else
        $this->scanDirectories($this->config->getSchemaDirectories());

        if ($this->isCacheValid()) {
            return $this->readSourceASTFromCache();
        }
        $source = $this->buildSourceAST();
        if ($this->config->isCacheEnabled()) {
            $this->writeSourceASTToCache($source);
            $this->writeDirectoryChangeCache();
        }

        return $source;
    }

    private function parseGraphQLFiles(): Source
    {
        $schemaFiles = array_keys($this->schemaFiles);

        return (new SchemaMerger())->merge($schemaFiles);
    }

    private function readSourceASTFromCache(): Node
    {
        return AST::fromArray(require $this->schemaCacheFile);
    }

    private function setFilesFromConfig(SchemaConfig $config): void
    {
        $cacheDirectory = $config->getCacheDirectory();
        $this->directoryChangeCacheFile = $cacheDirectory . '/' . $config->getDirectoryChangeFilename();
        $this->schemaCacheFile = $cacheDirectory . '/' . $config->getSchemaFilename();
    }

    private function writeDirectoryChangeCache(): void
    {
        $content = "<?php\nreturn " . var_export($this->schemaFiles, true) . ";\n";
        @file_put_contents($this->directoryChangeCacheFile, $content);
    }

    private function writeSourceASTToCache(DocumentNode $source): void
    {
        @file_put_contents($this->schemaCacheFile, "<?php\nreturn " . var_export(AST::toArray($source), true) . ";\n");
    }

    private function scanDirectories(array $directories): void
    {
        $subDirectories = [];
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $this->scanDirectory($directory, $subDirectories);
        }

        if (!empty($subDirectories)) {
            $this->scanDirectories($subDirectories);
        }
    }

    private function scanDirectory(string $directory, array &$subDirectories): void
    {
        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $subDirectories[] = $path;
            } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'graphql') {
                $this->schemaFiles[$path] = filemtime($path);
            }
        }
    }

    protected function getSchemaFiles(): array
    {
        if (empty($this->schemaFiles)) {
            $this->scanDirectories($this->config->getSchemaDirectories());
        }

        return array_keys($this->schemaFiles);
    }
}
