<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Factory;

use GraphQL\Middleware\Tests\TestCase;

function class_exists(string $class): bool
{
    if (isset($GLOBALS['mock_class_exists'])) {
        return $GLOBALS['mock_class_exists']->class_exists($class) ?? false;
    }
    return \class_exists($class);
}
