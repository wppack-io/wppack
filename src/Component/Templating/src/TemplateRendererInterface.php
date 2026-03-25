<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
