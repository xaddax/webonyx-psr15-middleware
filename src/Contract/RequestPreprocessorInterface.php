<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Contract;

use Psr\Http\Message\ServerRequestInterface;

interface RequestPreprocessorInterface
{
    /**
     * Process the request before it is handled by the GraphQL server.
     * This can be used to modify the request or perform validation.
     */
    public function process(ServerRequestInterface $request): ServerRequestInterface;
}
