<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Domain;

use GraphQL\Middleware\Domain\BaseEntity;

class Post extends BaseEntity
{
    public function __construct(
        private readonly string $id,
        private readonly string $title,
        private readonly string $content,
        private readonly User $author,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }
}
