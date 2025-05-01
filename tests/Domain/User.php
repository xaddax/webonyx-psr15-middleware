<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Domain;

use GraphQL\Middleware\Domain\BaseEntity;

class User extends BaseEntity
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly ?int $age = null,
        private readonly ?string $email = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }
}
