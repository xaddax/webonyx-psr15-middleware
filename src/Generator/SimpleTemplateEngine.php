<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Generator;

use GraphQL\Middleware\Contract\TemplateEngineInterface;
use GraphQL\Middleware\Exception\TemplateRenderException;

class SimpleTemplateEngine implements TemplateEngineInterface
{
    public function render(string $template, array $variables): string
    {
        try {
            $result = preg_replace_callback(
                '/\{\{\s*([^\}]+?)\s*\}\}/',
                function (array $matches) use ($variables): string {
                    $key = trim($matches[1]);
                    if (!isset($variables[$key])) {
                        return $matches[0];
                    }
                    $value = $variables[$key];
                    return is_scalar($value) ? (string) $value : '';
                },
                $template
            );

            if ($result === null) {
                throw new TemplateRenderException('Failed to render template: preg_replace_callback returned null');
            }

            return $result;
        } catch (\Throwable $e) {
            throw new TemplateRenderException('Failed to render template: ' . $e->getMessage(), 0, $e);
        }
    }

    public function supports(string $template): bool
    {
        return str_ends_with($template, '.template');
    }
}
