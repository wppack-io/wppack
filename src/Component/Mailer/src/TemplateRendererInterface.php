<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer;

interface TemplateRendererInterface
{
    /**
     * Render a template with the given context.
     *
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = []): string;
}
