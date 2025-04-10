<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Factory;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\AST;
use GraphQL\Middleware\Contract\SchemaConfigurationInterface;

class GeneratedSchemaFactory
{
    private bool $cacheEnabled = false;
    private string $directoryChangeCacheFile;
    private array $parserOptions;
    private string $schemaCacheFile;
    private array $schemaDirectories;
    private array $schemaFiles = [];

    public function __construct(
        private readonly SchemaConfigurationInterface $config
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
        return BuildSchema::build($source);
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
        $this->writeSourceASTToCache($source);
        $this->writeDirectoryChangeCache();

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
        file_put_contents($this->directoryChangeCacheFile, $content);
    }

    private function writeSourceASTToCache(DocumentNode $source): void
    {
        file_put_contents($this->schemaCacheFile, "<?php\nreturn " . var_export(AST::toArray($source), true) . ";\n");
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
        $dh = opendir($directory);
        if ($dh === false) {
            return;
        }

        try {
            while (($file = readdir($dh)) !== false) {
                $filePath = $directory . '/' . $file;
                $info = pathinfo($filePath);

                $this->processGraphQLFile($filePath, $info);
                $this->collectSubdirectory($filePath, $info, $subDirectories);
            }
        } finally {
            closedir($dh);
        }
    }

    private function processGraphQLFile(string $filePath, array $info): void
    {
        if (!isset($info['extension']) || $info['extension'] !== 'graphql') {
            return;
        }

        $key = realpath($filePath);
        if ($key === false) {
            throw new \RuntimeException(sprintf('Could not resolve real path for: %s', $filePath));
        }

        $lastModified = @filemtime($key);
        if ($lastModified === false) {
            throw new \RuntimeException(sprintf('Could not get modification time of file: %s', $key));
        }

        $this->schemaFiles[$key] = $lastModified;
    }

    private function collectSubdirectory(string $filePath, array $info, array &$subDirectories): void
    {
        if ($info['basename'] === $info['filename']) {
            $subDirectories[] = $filePath;
        }
    }
}
