<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Request;

use GraphQL\Middleware\Contract\RequestInterface;
use GraphQL\Middleware\Traits\ToArrayTrait;

/**
 * Base class for all generated request classes
 * @uses ToArrayTrait<RequestInterface>
 */
abstract class BaseRequest implements RequestInterface
{
    /** @use ToArrayTrait<RequestInterface> */
    use ToArrayTrait;

    protected function getToArrayInterface(): string
    {
        return RequestInterface::class;
    }
}
