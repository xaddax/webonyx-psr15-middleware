<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Factory;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\Parser;
use GraphQL\Middleware\Config\SchemaConfig;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\AST;

class GeneratedSchemaFactory
{
    private bool $cacheEnabled = false;
    private string $directoryChangeCacheFile;
    private array $parserOptions;
    private string $schemaCacheFile;
    private array $schemaDirectories;
    private array $schemaFiles = [];

    public function __construct(
        private readonly SchemaConfig $config
    ) {
        $this->cacheEnabled = $this->config->isCacheEnabled();
        $this->schemaDirectories = $this->config->getSchemaDirectories();
        $cacheDirectory = $this->config->getCacheDirectory();
        $this->directoryChangeCacheFile = $cacheDirectory . '/' . $this->config->getDirectoryChangeFilename();
        $this->schemaCacheFile = $cacheDirectory . '/' . $this->config->getSchemaFilename();
        $this->parserOptions = $this->config->getParserOptions();
    }

    public function createSchema(): Schema
    {
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
            $this->cacheEnabled &&
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
        $source = $this->readGraphQLFiles();

        return Parser::parse($source, $this->parserOptions);
    }

    private function getSourceAST(): Node
    {
        // the directories need to be scanned for both cache checking
        // and source building, so do it before anything else
        $this->scanDirectories($this->schemaDirectories);

        if ($this->isCacheValid()) {
            return $this->readSourceASTFromCache();
        }
        $source = $this->buildSourceAST();
        if ($this->cacheEnabled) {
            $this->writeSourceASTToCache($source);
            $this->writeDirectoryChangeCache();
        }

        return $source;
    }

    private function readGraphQLFiles(): string
    {
        $source = '';
        $schemaFiles = array_keys($this->schemaFiles);
        foreach ($schemaFiles as $file) {
            $source .= file_get_contents($file);
        }

        return $source;
    }

    private function readSourceASTFromCache(): Node
    {
        return AST::fromArray(require $this->schemaCacheFile);
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
            $this->scanDirectories($this->schemaDirectories);
        }
        return array_keys($this->schemaFiles);
    }
}
