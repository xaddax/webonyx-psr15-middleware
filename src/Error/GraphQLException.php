<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Error;

use GraphQL\Error\ClientAware;
use RuntimeException;

class GraphQLException extends RuntimeException implements ClientAware
{
    private bool $isClientSafe;
    private ?string $category;
    private array $extensions;

    public function __construct(
        string $message,
        bool $isClientSafe = true,
        ?string $category = null,
        array $extensions = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->isClientSafe = $isClientSafe;
        $this->category = $category;
        $this->extensions = $extensions;
    }

    public function isClientSafe(): bool
    {
        return $this->isClientSafe;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }
}
