<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Contract;

/**
 * Interface for all generated entity classes
 */
interface EntityInterface
{
    /**
     * Convert the entity to an array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
