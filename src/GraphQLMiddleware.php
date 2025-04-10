<?php

declare(strict_types=1);

namespace GraphQL\Middleware;

use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Middleware\Contract\RequestContextInterface;
use GraphQL\Middleware\Contract\ErrorHandlerInterface;
use GraphQL\Middleware\Contract\RequestPreprocessorInterface;
use GraphQL\Middleware\Contract\ResponseFactoryInterface;
use GraphQL\Middleware\Error\DefaultErrorHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GraphQLMiddleware implements MiddlewareInterface
{
    private readonly ErrorHandlerInterface $errorHandler;

    public function __construct(
        private readonly ServerConfig $serverConfig,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly array $allowedContentTypes = ['application/json'],
        private readonly ?RequestPreprocessorInterface $requestPreprocessor = null,
        ?ErrorHandlerInterface $errorHandler = null
    ) {
        $this->errorHandler = $errorHandler ?? new DefaultErrorHandler();
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

        try {
            $result = $this->executeRequest($request);
        } catch (\Exception $e) {
            $error = new Error($e->getMessage(), null, null, [], null, $e);
            $result = ['errors' => [$this->errorHandler->handleError($error, $request)]];
            $statusCode = $this->errorHandler->getStatusCode($error);
            $response = $this->responseFactory->createResponseWithData($result, $statusCode);
            return $response;
        }

        $data = $this->convertResultToResponseData($result);

        $response = $this->responseFactory->createResponseWithData($data, 200);

        return $response;
    }

    private function convertResultToResponseData(mixed $result): array
    {
        if ($result instanceof ExecutionResult) {
            return $result->toArray();
        }
        if (is_array($result)) {
            return array_map(fn(ExecutionResult $r) => $r->toArray(), $result);
        }
        throw new \RuntimeException('Async execution not supported');
    }

    /**
     * @return ExecutionResult|array<int,ExecutionResult>|Promise
     * @throws Error
     */
    private function executeRequest(ServerRequestInterface $request)
    {
        $server = new StandardServer($this->serverConfig);

        // Set request on context if available
        $context = $this->serverConfig->getContext();
        if ($context instanceof RequestContextInterface) {
            $context->setRequest($request);
        }

        /** @var ExecutionResult|array<int,ExecutionResult>|Promise $result */
        $result = $server->executePsrRequest($request);
        return $result;
    }

    private function isGraphQLRequest(ServerRequestInterface $request): bool
    {
        if (!$request->hasHeader('content-type')) {
            return false;
        }

        $contentType = trim($request->getHeaderLine("content-type"));

        if (!in_array($contentType, $this->allowedContentTypes, true)) {
            return false;
        }

        return true;
    }
}
