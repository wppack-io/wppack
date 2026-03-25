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

use WpPack\Component\Escaper\Escaper;
use WpPack\Component\Templating\Exception\RenderingException;
use WpPack\Component\Templating\Exception\TemplateNotFoundException;

/**
 * PHP template engine with layout inheritance, sections, and escaping.
 *
 * Templates are plain PHP files where $view refers to a TemplateContext instance,
 * providing escape helpers, layout declaration, sections, and partial includes.
 */
final class PhpRenderer implements TemplateRendererInterface
{
    private const MAX_RENDER_DEPTH = 20;

    private readonly TemplateLocator $locator;
    private readonly Escaper $escaper;
    private int $renderDepth = 0;

    /**
     * @param list<string> $paths Template search paths
     */
    public function __construct(
        array $paths = [],
        ?TemplateLocator $locator = null,
        ?Escaper $escaper = null,
    ) {
        $this->locator = $locator ?? new TemplateLocator($paths);
        $this->escaper = $escaper ?? new Escaper();
    }

    /**
     * Render a PHP template with the given context variables.
     *
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = [], string $variant = ''): string
    {
        $file = $this->locator->locate($template, $variant);

        if ($file === null) {
            throw new TemplateNotFoundException($template);
        }

        if ($this->renderDepth >= self::MAX_RENDER_DEPTH) {
            throw new RenderingException(
                sprintf(
                    'Maximum template nesting depth of %d exceeded. Check for circular layout references.',
                    self::MAX_RENDER_DEPTH,
                ),
            );
        }

        $this->renderDepth++;

        try {
            $templateContext = new TemplateContext($this->escaper, $this);
            $content = $this->renderFile($file, $context, $templateContext);

            // Handle layout inheritance
            /** @var array<string, true> $visitedLayouts */
            $visitedLayouts = [];
            $layoutTemplate = $templateContext->getLayoutTemplate();

            while ($layoutTemplate !== null) {
                if (isset($visitedLayouts[$layoutTemplate])) {
                    throw new RenderingException(
                        sprintf('Circular layout reference detected: "%s" has already been rendered.', $layoutTemplate),
                    );
                }

                $visitedLayouts[$layoutTemplate] = true;
                $templateContext->setSection('content', $content);
                $layoutFile = $this->locator->locate($layoutTemplate, $templateContext->getLayoutVariant());

                if ($layoutFile === null) {
                    throw new TemplateNotFoundException($layoutTemplate);
                }

                // Reset layout so we can detect if the layout sets another one
                $templateContext->resetLayout();
                $content = $this->renderFile($layoutFile, $context, $templateContext);
                $layoutTemplate = $templateContext->getLayoutTemplate();
            }
        } finally {
            $this->renderDepth--;
        }

        return $content;
    }

    /**
     * Render and directly output a template.
     *
     * @param array<string, mixed> $context
     */
    public function display(string $template, array $context = [], string $variant = ''): void
    {
        echo $this->render($template, $context, $variant);
    }

    /**
     * Check if a template exists.
     */
    public function exists(string $template, string $variant = ''): bool
    {
        return $this->locator->locate($template, $variant) !== null;
    }

    /**
     * Check if this renderer supports the given template.
     *
     * Returns true if the template can be located as a .php file.
     */
    public function supports(string $template): bool
    {
        return $this->locator->locate($template) !== null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderFile(string $file, array $context, TemplateContext $templateContext): string
    {
        $render = static function (string $__file, array $__context, TemplateContext $view): string {
            extract($__context, EXTR_SKIP);
            ob_start();

            try {
                include $__file;
            } catch (\Throwable $e) {
                ob_end_clean();

                throw $e;
            }

            return ob_get_clean() ?: '';
        };

        return $render($file, $context, $templateContext);
    }
}
