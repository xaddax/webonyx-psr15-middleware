<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Request;

use GraphQL\Middleware\Contract\RequestInterface;

/**
 * Base class for all generated request classes
 */
abstract class BaseRequest implements RequestInterface
{
    /**
     * Convert the request to an array
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

            if ($value instanceof RequestInterface) {
                $value = $value->toArray();
            } elseif (is_array($value)) {
                $value = array_map(
                    fn($item) => $item instanceof RequestInterface ? $item->toArray() : $item,
                    $value
                );
            }

            $data[$property->getName()] = $value;
        }

        return $data;
    }
}
