<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Traits;

use GraphQL\Middleware\Traits\ToArrayTrait;

/**
 * @uses ToArrayTrait<DummyEntity>
 */
class DummyEntity
{
    /** @use ToArrayTrait<DummyEntity> */
    use ToArrayTrait;

    /**
     * @var string
     * @used-by ToArrayTrait::toArray()
     * @phpstan-ignore-next-line Property is accessed via reflection in ToArrayTrait
     */
    private string $foo;

    /**
     * @var array<mixed>
     * @used-by ToArrayTrait::toArray()
     * @phpstan-ignore-next-line Property is accessed via reflection in ToArrayTrait
     */
    private array $bar;

    /**
     * @var DummyEntity|null
     * @used-by ToArrayTrait::toArray()
     * @phpstan-ignore-next-line Property is accessed via reflection in ToArrayTrait
     */
    private ?DummyEntity $child;

    public function __construct(string $foo, array $bar = [], ?DummyEntity $child = null)
    {
        $this->foo = $foo;
        $this->bar = $bar;
        $this->child = $child;
    }

    protected function getToArrayInterface(): string
    {
        return self::class;
    }
}
