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

namespace WPPack\Component\Debug\Toolbar\Panel;

use WPPack\Component\Debug\Profiler\Profile;
use WPPack\Component\Templating\PhpRenderer;

abstract class AbstractPanelRenderer
{
    /** @var array<string, array{bg: string, fg: string}> */
    private const INDICATOR_COLORS = [
        'green' => ['bg' => 'var(--wpd-green-a12)', 'fg' => 'var(--wpd-green)'],
        'yellow' => ['bg' => 'var(--wpd-yellow-a12)', 'fg' => 'var(--wpd-yellow)'],
        'red' => ['bg' => 'var(--wpd-red-a12)', 'fg' => 'var(--wpd-red)'],
        'default' => ['bg' => 'transparent', 'fg' => 'var(--wpd-gray-800)'],
    ];

    protected float $requestTimeFloat = 0.0;

    private ?PhpRenderer $lazyPhpRenderer = null;
    private ?TemplateFormatters $lazyFormatters = null;

    public function __construct(
        protected readonly Profile $profile,
        private readonly ?PhpRenderer $phpRenderer = null,
        private readonly ?TemplateFormatters $templateFormatters = null,
    ) {}

    abstract public function getName(): string;

    public function isEnabled(): bool
    {
        return true;
    }

    public function renderIndicator(): string
    {
        try {
            $collector = $this->profile->getCollector($this->getName());
        } catch (\Throwable) {
            return '';
        }

        return $this->getPhpRenderer()->render('toolbar/indicators/default', [
            'name' => $collector->getName(),
            'label' => $collector->getLabel(),
            'value' => $collector->getIndicatorValue(),
            'colorKey' => $collector->getIndicatorColor(),
            'colors' => self::INDICATOR_COLORS[$collector->getIndicatorColor()] ?? self::INDICATOR_COLORS['default'],
            'icon' => ToolbarIcons::svg($collector->getName()),
        ]);
    }

    public function setRequestTimeFloat(float $requestTimeFloat): void
    {
        $this->requestTimeFloat = $requestTimeFloat;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCollectorData(?string $name = null): array
    {
        try {
            return $this->profile->getCollector($name ?? $this->getName())->getData();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function getPhpRenderer(): PhpRenderer
    {
        if ($this->phpRenderer !== null) {
            return $this->phpRenderer;
        }

        return $this->lazyPhpRenderer ??= new PhpRenderer([
            dirname(__DIR__, 3) . '/templates',
        ]);
    }

    protected function getFormatters(): TemplateFormatters
    {
        if ($this->templateFormatters !== null) {
            return $this->templateFormatters;
        }

        return $this->lazyFormatters ??= new TemplateFormatters();
    }

    /**
     * @return array<string, array{bg: string, fg: string}>
     */
    public static function getIndicatorColors(): array
    {
        return self::INDICATOR_COLORS;
    }
}
