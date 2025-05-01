<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Domain;

use GraphQL\Middleware\Contract\EntityInterface;

/**
 * Base class for all generated entity classes
 */
abstract class BaseEntity implements EntityInterface
{
    /**
     * Convert the entity to an array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PRIVATE);

        $data = [];
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($this);

            if ($value instanceof EntityInterface) {
                $value = $value->toArray();
            } elseif (is_array($value)) {
                $value = array_map(
                    fn($item) => $item instanceof EntityInterface ? $item->toArray() : $item,
                    $value
                );
            }

            $data[$property->getName()] = $value;
        }

        return $data;
    }
}
