<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Traits;

use PHPUnit\Framework\TestCase;

class ToArrayTraitTest extends TestCase
{
    public function testSimpleToArray(): void
    {
        $entity = new DummyEntity('value', ['a', 'b']);
        $expected = [
            'foo' => 'value',
            'bar' => ['a', 'b'],
            'child' => null,
        ];
        $this->assertSame($expected, $entity->toArray());
    }

    public function testNestedEntityToArray(): void
    {
        $child = new DummyEntity('child', ['x']);
        $entity = new DummyEntity('parent', ['y'], $child);
        $expected = [
            'foo' => 'parent',
            'bar' => ['y'],
            'child' => [
                'foo' => 'child',
                'bar' => ['x'],
                'child' => null,
            ],
        ];
        $this->assertSame($expected, $entity->toArray());
    }

    public function testArrayOfEntities(): void
    {
        $child1 = new DummyEntity('c1');
        $child2 = new DummyEntity('c2');
        $entity = new DummyEntity('parent', [$child1, $child2]);
        $expected = [
            'foo' => 'parent',
            'bar' => [
                [
                    'foo' => 'c1',
                    'bar' => [],
                    'child' => null,
                ],
                [
                    'foo' => 'c2',
                    'bar' => [],
                    'child' => null,
                ],
            ],
            'child' => null,
        ];
        $this->assertSame($expected, $entity->toArray());
    }
}
