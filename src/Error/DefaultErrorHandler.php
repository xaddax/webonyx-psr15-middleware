<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Error;

use GraphQL\Error\Error;
use GraphQL\Middleware\Contract\ErrorHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;

class DefaultErrorHandler implements ErrorHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function handleError(Error $error, ServerRequestInterface $request): array
    {
        $result = [
            'message' => $error->getMessage(),
        ];

        $locations = $error->getLocations();
        if (!empty($locations)) {
            $result['locations'] = array_map(
                fn ($location) => [
                    'line' => (int) $location->line,
                    'column' => (int) $location->column,
                ],
                $locations
            );
        }

        $extensions = $error->getExtensions();
        if ($extensions) {
            $result['extensions'] = $extensions;
        }

        return $result;
    }

    public function getStatusCode(Error $error): int
    {
        $extensions = $error->getExtensions();
        if (isset($extensions['statusCode']) && is_numeric($extensions['statusCode'])) {
            return (int) $extensions['statusCode'];
        }
        if (isset($extensions['status']) && is_numeric($extensions['status'])) {
            return (int) $extensions['status'];
        }
        return 200;
    }
}
