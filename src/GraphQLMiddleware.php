<?php

declare(strict_types=1);

namespace GraphQL\Middleware;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use GraphQL\Middleware\Contract\ResponseFactoryInterface;

final class GraphQLMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ServerConfig $serverConfig,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly array $allowedHeaders = [],
        private readonly ?RequestPreprocessorInterface $requestPreprocessor = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->isGraphQLRequest($request)) {
            return $handler->handle($request);
        }

        if (empty($request->getParsedBody())) {
            $json = (string) $request->getBody();
            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->responseFactory->createErrorResponse([
                    ['message' => 'Invalid JSON: ' . json_last_error_msg()]
                ], 400);
            }

            if (!is_array($data)) {
                return $this->responseFactory->createErrorResponse([
                    ['message' => 'Invalid JSON: Expected object or array']
                ], 400);
            }

            $request = $request->withParsedBody($data);
        }

        if ($this->requestPreprocessor) {
            try {
                $request = $this->requestPreprocessor->process($request);
            } catch (\Exception $exception) {
                return $this->responseFactory->createErrorResponse([
                    'message' => $exception->getMessage(),
                ], 401);
            }
        }

        $result = $this->executeRequest($request);

        return $this->responseFactory->createResponse($result->toArray());
    }

    /**
     * @return ExecutionResult
     * @throws \Exception
     */
    private function executeRequest(ServerRequestInterface $request): ExecutionResult
    {
        $context = $this->serverConfig->getContext();
        if ($context instanceof RequestContextInterface) {
            $context->setRequest($request);
        }

        /** @var ExecutionResult $result */
        $result = (new StandardServer($this->serverConfig))->executePsrRequest($request);

        return $result;
    }

    private function isGraphQLRequest(ServerRequestInterface $request): bool
    {
        if (!$request->hasHeader('content-type')) {
            return false;
        }

        $requestHeaderList = array_map(
            function ($header) {
                return trim($header);
            },
            explode(",", $request->getHeaderLine("content-type"))
        );

        foreach ($this->allowedHeaders as $allowedHeader) {
            if (in_array($allowedHeader, $requestHeaderList)) {
                return true;
            }
        }

        return false;
    }
}
