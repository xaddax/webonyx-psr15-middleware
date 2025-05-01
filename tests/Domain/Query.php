<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Domain;

use GraphQL\Middleware\Domain\BaseEntity;

class Query extends BaseEntity
{
    public function __construct(
        private readonly ?User $user = null,
    ) {
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
