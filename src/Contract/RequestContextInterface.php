<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Contract;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Server\OperationParams;
use Psr\Http\Message\ServerRequestInterface;

interface RequestContextInterface
{
    /**
     * Handle the GraphQL operation
     */
    public function __invoke(OperationParams $params, DocumentNode $doc, string $operationType): mixed;

    /**
     * Set the current request on the context
     */
    public function setRequest(ServerRequestInterface $request): void;
}
