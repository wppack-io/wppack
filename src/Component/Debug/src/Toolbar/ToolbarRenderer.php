<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar;

use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\Panel\AbstractPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\CachePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\DatabasePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\DumpPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\EventPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\GenericPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\HttpClientPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\LoggerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\MailPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\MemoryPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\PanelRendererInterface;
use WpPack\Component\Debug\Toolbar\Panel\PerformancePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\PluginPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RequestPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RouterPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\SchedulerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\ThemePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\TimePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\ToolbarAssets;
use WpPack\Component\Debug\Toolbar\Panel\TranslationPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\UserPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\WordPressPanelRenderer;

final class ToolbarRenderer
{
    private const ICONS = [
        'performance' => "\xF0\x9F\x9A\x80",
        'request' => "\xF0\x9F\x8C\x90",
        'database' => "\xF0\x9F\x92\xBE",
        'memory' => "\xF0\x9F\x93\x8A",
        'time' => "\xE2\x8F\xB1\xEF\xB8\x8F",
        'cache' => "\xF0\x9F\x93\xA6",
        'wordpress' => "\xE2\x9A\x99\xEF\xB8\x8F",
        'user' => "\xF0\x9F\x91\xA4",
        'mail' => "\xE2\x9C\x89\xEF\xB8\x8F",
        'event' => "\xF0\x9F\x94\x94",
        'logger' => "\xF0\x9F\x93\x9D",
        'router' => "\xF0\x9F\x9B\xA4\xEF\xB8\x8F",
        'http_client' => "\xF0\x9F\x94\x97",
        'translation' => "\xF0\x9F\x94\xA0",
        'dump' => "\xF0\x9F\x93\x8C",
        'plugin' => "\xF0\x9F\x94\x8C",
        'theme' => "\xF0\x9F\x8E\xA8",
        'scheduler' => "\xE2\x8F\xB0",
    ];

    private const DEFAULT_ICON = "\xF0\x9F\x93\x8B";

    private const BADGE_COLORS = [
        'green' => '#a6e3a1',
        'yellow' => '#f9e2af',
        'red' => '#f38ba8',
        'default' => '#cdd6f4',
    ];

    /** @var array<string, AbstractPanelRenderer&PanelRendererInterface> */
    private readonly array $panelRenderers;

    private readonly PerformancePanelRenderer $performanceRenderer;

    private readonly GenericPanelRenderer $genericRenderer;

    private readonly ToolbarAssets $assets;

    public function __construct()
    {
        $renderers = [
            new DatabasePanelRenderer(),
            new TimePanelRenderer(),
            new MemoryPanelRenderer(),
            new RequestPanelRenderer(),
            new CachePanelRenderer(),
            new WordPressPanelRenderer(),
            new UserPanelRenderer(),
            new MailPanelRenderer(),
            new EventPanelRenderer(),
            new LoggerPanelRenderer(),
            new RouterPanelRenderer(),
            new HttpClientPanelRenderer(),
            new TranslationPanelRenderer(),
            new DumpPanelRenderer(),
            new PluginPanelRenderer(),
            new ThemePanelRenderer(),
            new SchedulerPanelRenderer(),
        ];

        $map = [];
        foreach ($renderers as $r) {
            $map[$r->getName()] = $r;
        }

        $this->panelRenderers = $map;
        $this->performanceRenderer = new PerformancePanelRenderer();
        $this->genericRenderer = new GenericPanelRenderer();
        $this->assets = new ToolbarAssets();
    }

    public function render(Profile $profile): string
    {
        $collectors = $profile->getCollectors();

        // Extract request_time_float for relative time display across panels
        $requestTimeFloat = 0.0;
        if (isset($collectors['time'])) {
            $timeData = $collectors['time']->getData();
            $requestTimeFloat = (float) ($timeData['request_time_float'] ?? 0.0);
        }

        // Propagate request_time_float to all panel renderers
        foreach ($this->panelRenderers as $renderer) {
            $renderer->setRequestTimeFloat($requestTimeFloat);
        }
        $this->performanceRenderer->setRequestTimeFloat($requestTimeFloat);
        $this->genericRenderer->setRequestTimeFloat($requestTimeFloat);

        $badges = '';
        $panels = '';

        foreach ($collectors as $name => $collector) {
            $badges .= $this->renderBadge($collector);
            $panels .= $this->renderPanel($collector);
        }

        $perfBadge = $this->performanceRenderer->renderBadge($profile);
        $perfPanel = $this->performanceRenderer->renderPanel($profile);
        $badges = $perfBadge . $badges;
        $panels = $perfPanel . $panels;

        $requestInfo = $this->esc($profile->getMethod()) . ' ' . $this->esc((string) $profile->getStatusCode());
        $totalTime = $this->formatMs($profile->getTime());

        $css = $this->assets->renderCss();
        $js = $this->assets->renderJs();

        return <<<HTML
        <div id="wppack-debug">
        <style>{$css}</style>
        {$panels}
        <div class="wpd-mini" title="Show WpPack Debug Toolbar">
            <span class="wpd-mini-logo">WP</span>
        </div>
        <div class="wpd-bar">
            <div class="wpd-bar-logo" title="WpPack Debug">
                <span class="wpd-logo-text">WP</span>
            </div>
            <div class="wpd-bar-badges">
                {$badges}
            </div>
            <div class="wpd-bar-meta">
                <span class="wpd-meta-item">{$requestInfo}</span>
                <span class="wpd-meta-sep">|</span>
                <span class="wpd-meta-item">{$totalTime}</span>
            </div>
            <button class="wpd-close-btn" data-action="minimize" title="Close toolbar">&times;</button>
        </div>
        <script>{$js}</script>
        </div>
        HTML;
    }

    private function renderBadge(DataCollectorInterface $collector): string
    {
        $name = $this->esc($collector->getName());
        $label = $this->esc($collector->getLabel());
        $value = $this->esc($collector->getBadgeValue());
        $colorKey = $collector->getBadgeColor();
        $color = self::BADGE_COLORS[$colorKey] ?? self::BADGE_COLORS['default'];
        $icon = self::ICONS[$collector->getName()] ?? self::DEFAULT_ICON;

        return <<<HTML
        <button class="wpd-badge" data-panel="{$name}" title="{$label}">
            <span class="wpd-badge-icon">{$icon}</span>
            <span class="wpd-badge-value" style="color:{$color}">{$value}</span>
        </button>
        HTML;
    }

    private function renderPanel(DataCollectorInterface $collector): string
    {
        $name = $this->esc($collector->getName());
        $label = $this->esc($collector->getLabel());
        $icon = self::ICONS[$collector->getName()] ?? self::DEFAULT_ICON;
        $content = $this->renderPanelContent($collector);

        return <<<HTML
        <div class="wpd-panel" id="wpd-panel-{$name}" style="display:none">
            <div class="wpd-panel-header">
                <span class="wpd-panel-title">{$icon} {$label}</span>
                <button class="wpd-panel-close" data-action="close-panel" title="Close">&times;</button>
            </div>
            <div class="wpd-panel-body">
                {$content}
            </div>
        </div>
        HTML;
    }

    private function renderPanelContent(DataCollectorInterface $collector): string
    {
        $renderer = $this->panelRenderers[$collector->getName()] ?? $this->genericRenderer;

        return $renderer->render($collector->getData());
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function formatMs(float $ms): string
    {
        if ($ms >= 1000) {
            return $this->esc(sprintf('%.2f s', $ms / 1000));
        }

        return $this->esc(sprintf('%.1f ms', $ms));
    }
}
