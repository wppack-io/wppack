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

/**
 * Template context object exposed as $this inside PHP templates.
 *
 * Provides escaping helpers, layout inheritance, sections, and partial includes
 * following the Plates template pattern.
 */
final class TemplateContext
{
    private ?string $layoutTemplate = null;
    private string $layoutVariant = '';

    /** @var array<string, string> */
    private array $sections = [];

    private ?string $openSection = null;

    public function __construct(
        private readonly Escaper $escaper,
        private readonly PhpRenderer $renderer,
    ) {}

    /**
     * Escape a value for safe output.
     *
     * Handles mixed types: null→'', int/float/Stringable→(string), bool→'1'/''.
     * Arrays and non-Stringable objects throw RenderingException.
     *
     * @param string $strategy One of: html, attr, url, js
     */
    public function e(mixed $value, string $strategy = 'html'): string
    {
        return $this->escaper->escape($this->convertToString($value), $strategy);
    }

    /**
     * Output a value without escaping.
     *
     * Use with caution — only for pre-escaped or trusted content.
     */
    public function raw(mixed $value): string
    {
        return $this->convertToString($value);
    }

    /**
     * Set the layout template for the current template.
     *
     * The layout will be rendered after the child template, with the child's
     * output available via $this->section('content').
     */
    public function layout(string $template, string $variant = ''): void
    {
        $this->layoutTemplate = $template;
        $this->layoutVariant = $variant;
    }

    /**
     * Start capturing a named section.
     */
    public function start(string $name): void
    {
        if ($this->openSection !== null) {
            throw new RenderingException(
                sprintf('Cannot start section "%s": section "%s" is already open.', $name, $this->openSection),
            );
        }

        $this->openSection = $name;
        ob_start();
    }

    /**
     * Stop capturing the current section.
     */
    public function stop(): void
    {
        if ($this->openSection === null) {
            throw new RenderingException('Cannot stop section: no section is currently open.');
        }

        $this->sections[$this->openSection] = ob_get_clean() ?: '';
        $this->openSection = null;
    }

    /**
     * Get the content of a named section.
     *
     * Returns $default if the section has not been defined.
     */
    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Render and include a partial template.
     *
     * @param array<string, mixed> $context
     */
    public function include(string $template, array $context = [], string $variant = ''): string
    {
        return $this->renderer->render($template, $context, $variant);
    }

    /**
     * @internal Used by PhpRenderer to check if a layout was set.
     */
    public function getLayoutTemplate(): ?string
    {
        return $this->layoutTemplate;
    }

    /**
     * @internal Used by PhpRenderer to get the layout variant.
     */
    public function getLayoutVariant(): string
    {
        return $this->layoutVariant;
    }

    /**
     * @internal Used by PhpRenderer to reset layout state between iterations.
     */
    public function resetLayout(): void
    {
        $this->layoutTemplate = null;
        $this->layoutVariant = '';
    }

    /**
     * @internal Used by PhpRenderer to inject child content as 'content' section.
     */
    public function setSection(string $name, string $content): void
    {
        $this->sections[$name] = $content;
    }

    private function convertToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (\is_string($value)) {
            return $value;
        }

        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        if (\is_bool($value)) {
            return $value ? '1' : '';
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        throw new RenderingException(
            sprintf('Cannot convert value of type "%s" to string for template output.', get_debug_type($value)),
        );
    }
}
