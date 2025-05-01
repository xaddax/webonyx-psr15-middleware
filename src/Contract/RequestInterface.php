<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Contract;

/**
 * Interface for all generated request classes
 */
interface RequestInterface
{
    /**
     * Convert the request to an array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
