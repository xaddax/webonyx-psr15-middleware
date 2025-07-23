<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Context;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Middleware\Contract\RequestContextInterface;
use GraphQL\Server\OperationParams;
use Psr\Http\Message\ServerRequestInterface;

class RequestContext implements RequestContextInterface
{
    private ?ServerRequestInterface $request = null;

    public function __invoke(OperationParams $params, DocumentNode $doc, string $operationType): mixed
    {
        // This method is called by the middleware but we don't need to do anything special here
        // The context is just a container for the request and other shared data
        return $this;
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function getRequest(): ServerRequestInterface
    {
        if ($this->request === null) {
            throw new \RuntimeException('Request not set on GraphQL context');
        }

        return $this->request;
    }

    public function getToken(): ?string
    {
        return $this->getRequest()->getHeaderLine('Authorization') ?: null;
    }

    public function getClientId(): ?string
    {
        return $this->getRequest()->getHeaderLine('X-CLIENT-ID') ?: null;
    }

    public function getUserAgent(): ?string
    {
        return $this->getRequest()->getHeaderLine('User-Agent') ?: null;
    }

    public function getMethod(): string
    {
        return $this->getRequest()->getMethod();
    }

    public function getQueryParams(): array
    {
        return $this->getRequest()->getQueryParams();
    }

    public function getParsedBody(): mixed
    {
        return $this->getRequest()->getParsedBody();
    }

    public function getCookieParams(): array
    {
        return $this->getRequest()->getCookieParams();
    }

    public function getServerParams(): array
    {
        return $this->getRequest()->getServerParams();
    }
}
