<?php
declare(strict_types=1);

namespace IamPersistent\GraphQL\Middleware;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Server\OperationParams;
use Psr\Http\Message\ServerRequestInterface;

interface RequestContextInterface
{
    public function __invoke(OperationParams $params, DocumentNode $doc, $operationType);

    public function setRequest(ServerRequestInterface $request);
}
