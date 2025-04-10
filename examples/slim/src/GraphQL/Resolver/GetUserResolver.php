<?php

declare(strict_types=1);

namespace App\GraphQL\Resolver;

use GraphQL\Middleware\Contract\ResolverInterface;
use GraphQL\Type\Definition\ResolveInfo;

class GetUserResolver implements ResolverInterface
{
    public function __invoke($source, array $args, $context, ResolveInfo $info): array
    {
        // Example implementation - in a real app, this would fetch from a database
        return [
            'id' => '1',
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];
    }
}
