<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Templating;

use WPPack\Component\Templating\Exception\TemplateNotFoundException;

/**
 * Delegates rendering to the first renderer that supports the template.
 *
 * Similar to Symfony's DelegatingEngine. Use this when multiple template
 * engines are in play (e.g., PhpRenderer + TwigRenderer).
 */
final class ChainRenderer implements TemplateRendererInterface
{
    /** @var list<TemplateRendererInterface> */
    private array $renderers;

    /** @param list<TemplateRendererInterface> $renderers */
    public function __construct(array $renderers = [])
    {
        $this->renderers = $renderers;
    }

    public function render(string $template, array $context = []): string
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($template)) {
                return $renderer->render($template, $context);
            }
        }

        throw new TemplateNotFoundException($template);
    }

    public function exists(string $template): bool
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->exists($template)) {
                return true;
            }
        }

        return false;
    }

    public function supports(string $template): bool
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($template)) {
                return true;
            }
        }

        return false;
    }

    public function addRenderer(TemplateRendererInterface $renderer): void
    {
        $this->renderers[] = $renderer;
    }
}
