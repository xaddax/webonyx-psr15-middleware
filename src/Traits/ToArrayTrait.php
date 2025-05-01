<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Traits;

/**
 * @template T of object
 */
trait ToArrayTrait
{
    /**
     * Returns the FQCN of the interface to check for recursion (must be set in the using class)
     * @return class-string<T>
     */
    abstract protected function getToArrayInterface(): string;

    /**
     * Convert the object to an array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PRIVATE);

        $data = [];
        $interface = $this->getToArrayInterface();
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($this);

            if (is_object($value) && is_a($value, $interface)) {
                /** @var T $value */
                $value = $value->toArray();
            } elseif (is_array($value)) {
                $value = array_map(
                    function ($item) use ($interface) {
                        if (is_object($item) && is_a($item, $interface)) {
                            /** @var T $item */
                            return $item->toArray();
                        }
                        return $item;
                    },
                    $value
                );
            }

            $data[$property->getName()] = $value;
        }

        return $data;
    }
}
