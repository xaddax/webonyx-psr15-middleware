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
        $formatted = [
            'message' => $error->getMessage(),
        ];

        // Handle locations
        $extensions = $error->getExtensions();
        if (isset($extensions['line'], $extensions['column'])) {
            $formatted['locations'] = [
                [
                    'line' => $extensions['line'],
                    'column' => $extensions['column'],
                ]
            ];
            // Remove location data from extensions
            unset($extensions['line'], $extensions['column']);
        }

        // Handle remaining extensions
        if ($extensions !== null) {
            $formatted['extensions'] = $extensions;
        }

        return $formatted;
    }

    public function getStatusCode(Error $error): int
    {
        $extensions = $error->getExtensions();
        if (isset($extensions['statusCode'])) {
            return (int) $extensions['statusCode'];
        }
        return 200;
    }
}
