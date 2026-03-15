<?php

declare(strict_types=1);

namespace WpPack\Component\Templating;

interface TemplateRendererInterface
{
    /**
     * Render a template with the given context variables.
     *
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = []): string;

    /**
     * Check if a template exists.
     */
    public function exists(string $template): bool;

    /**
     * Check if this renderer supports the given template.
     */
    public function supports(string $template): bool;
}
