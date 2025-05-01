<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Config;

abstract class GeneratedClassConfig
{
    private readonly string $namespace;
    private readonly string $fileLocation;
    private readonly string $templatePath;

    public function __construct(array $config)
    {
        if (!isset($config['namespace']) || !is_string($config['namespace'])) {
            throw new \InvalidArgumentException('namespace must be a string');
        }
        if (!isset($config['fileLocation']) || !is_string($config['fileLocation'])) {
            throw new \InvalidArgumentException('fileLocation must be a string');
        }
        if (!isset($config['templatePath']) || !is_string($config['templatePath'])) {
            throw new \InvalidArgumentException('templatePath must be a string');
        }

        $this->namespace = $config['namespace'];
        $this->fileLocation = $config['fileLocation'];
        $this->templatePath = $config['templatePath'];
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getFileLocation(): string
    {
        return $this->fileLocation;
    }

    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    public function toArray(): array
    {
        return [
            'namespace' => $this->namespace,
            'fileLocation' => $this->fileLocation,
            'templatePath' => $this->templatePath,
        ];
    }
}
