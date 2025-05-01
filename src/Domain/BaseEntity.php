<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Domain;

use GraphQL\Middleware\Contract\EntityInterface;
use GraphQL\Middleware\Traits\ToArrayTrait;

/**
 * Base class for all generated entity classes
 * @uses ToArrayTrait<EntityInterface>
 */
abstract class BaseEntity implements EntityInterface
{
    /** @use ToArrayTrait<EntityInterface> */
    use ToArrayTrait;

    protected function getToArrayInterface(): string
    {
        return EntityInterface::class;
    }
}
