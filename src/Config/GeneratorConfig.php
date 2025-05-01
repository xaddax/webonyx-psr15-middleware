<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Config;

class GeneratorConfig
{
    private readonly EntityConfig $entityConfig;
    private readonly RequestConfig $requestConfig;
    private readonly ResolverConfig $resolverConfig;
    /** @var array<string, string> */
    private readonly array $typeMappings;
    /** @var array<string, mixed> */
    private readonly array $customTypes;
    private readonly bool $isImmutable;
    private readonly bool $hasStrictTypes;

    public function __construct(array $config)
    {
        if (!isset($config['entityConfig']) || !is_array($config['entityConfig'])) {
            throw new \InvalidArgumentException('entityConfig must be an array');
        }
        if (!isset($config['requestConfig']) || !is_array($config['requestConfig'])) {
            throw new \InvalidArgumentException('requestConfig must be an array');
        }
        if (!isset($config['resolverConfig']) || !is_array($config['resolverConfig'])) {
            throw new \InvalidArgumentException('resolverConfig must be an array');
        }
        if (!isset($config['typeMappings']) || !is_array($config['typeMappings'])) {
            throw new \InvalidArgumentException('typeMappings must be an array');
        }
        if (!isset($config['customTypes']) || !is_array($config['customTypes'])) {
            throw new \InvalidArgumentException('customTypes must be an array');
        }
        if (!isset($config['isImmutable']) || !is_bool($config['isImmutable'])) {
            throw new \InvalidArgumentException('isImmutable must be a boolean');
        }
        if (!isset($config['hasStrictTypes']) || !is_bool($config['hasStrictTypes'])) {
            throw new \InvalidArgumentException('hasStrictTypes must be a boolean');
        }

        $this->entityConfig = new EntityConfig($config['entityConfig']);
        $this->requestConfig = new RequestConfig($config['requestConfig']);
        $this->resolverConfig = new ResolverConfig($config['resolverConfig']);
        $this->typeMappings = $this->validateTypeMappings($config['typeMappings']);
        $this->customTypes = $config['customTypes'];
        $this->isImmutable = $config['isImmutable'];
        $this->hasStrictTypes = $config['hasStrictTypes'];
    }

    public function getEntityConfig(): EntityConfig
    {
        return $this->entityConfig;
    }

    public function getRequestConfig(): RequestConfig
    {
        return $this->requestConfig;
    }

    public function getResolverConfig(): ResolverConfig
    {
        return $this->resolverConfig;
    }

    /**
     * @return array<string, string>
     */
    public function getTypeMappings(): array
    {
        return $this->typeMappings;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomTypes(): array
    {
        return $this->customTypes;
    }

    public function isImmutable(): bool
    {
        return $this->isImmutable;
    }

    public function hasStrictTypes(): bool
    {
        return $this->hasStrictTypes;
    }

    public function toArray(): array
    {
        return [
            'entityConfig' => $this->entityConfig->toArray(),
            'requestConfig' => $this->requestConfig->toArray(),
            'resolverConfig' => $this->resolverConfig->toArray(),
            'typeMappings' => $this->typeMappings,
            'customTypes' => $this->customTypes,
            'isImmutable' => $this->isImmutable,
            'hasStrictTypes' => $this->hasStrictTypes,
        ];
    }

    /**
     * @param array<string, mixed> $typeMappings
     * @return array<string, string>
     */
    private function validateTypeMappings(array $typeMappings): array
    {
        $validatedMappings = [];
        foreach ($typeMappings as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Type mapping keys must be strings');
            }
            if (!is_string($value)) {
                throw new \InvalidArgumentException('Type mapping values must be strings');
            }
            $validatedMappings[$key] = $value;
        }

        return $validatedMappings;
    }
}
