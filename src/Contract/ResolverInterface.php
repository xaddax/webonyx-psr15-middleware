<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Contract;

use GraphQL\Type\Definition\ResolveInfo;

interface ResolverInterface
{
    /**
     * @param mixed $source The parent/source value
     * @param array<string, mixed> $args Field arguments
     * @param mixed $context Shared context passed to all resolvers
     * @param ResolveInfo $info Field resolution information
     * @return mixed The resolved value
     */
    public function __invoke($source, array $args, $context, ResolveInfo $info): mixed;
}
