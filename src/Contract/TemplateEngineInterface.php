<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Contract;

interface TemplateEngineInterface
{
    /**
     * Render a template with given variables
     *
     * @param string $template The template content or path
     * @param array<string, mixed> $variables Variables to inject into template
     * @throws \GraphQL\Middleware\Exception\TemplateRenderException If rendering fails
     */
    public function render(string $template, array $variables): string;

    /**
     * Check if this engine can handle the given template
     */
    public function supports(string $template): bool;
}
