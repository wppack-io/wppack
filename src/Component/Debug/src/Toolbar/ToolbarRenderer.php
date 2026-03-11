<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar;

use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\Panel\AbstractPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\GenericPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\PanelRendererInterface;
use WpPack\Component\Debug\Toolbar\Panel\PerformancePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\ToolbarAssets;
use WpPack\Component\Debug\Toolbar\Panel\ToolbarIcons;

final class ToolbarRenderer
{
    private const BADGE_COLORS = [
        'green' => '#1e1e1e',
        'yellow' => '#996800',
        'red' => '#cc1818',
        'default' => '#50575e',
    ];

    /** Sidebar panel order, grouped by category. */
    private const SIDEBAR_GROUPS = [
        ['performance'],
        ['request', 'time', 'memory', 'database', 'cache', 'http_client'],
        ['wordpress', 'plugin', 'theme', 'router'],
        ['event', 'logger', 'dump', 'mail', 'scheduler', 'translation', 'user'],
    ];

    /** @var array<string, AbstractPanelRenderer&PanelRendererInterface> */
    private array $panelRenderers = [];

    private readonly PerformancePanelRenderer $performanceRenderer;

    private readonly GenericPanelRenderer $genericRenderer;

    private readonly ToolbarAssets $assets;

    public function __construct()
    {
        $this->performanceRenderer = new PerformancePanelRenderer();
        $this->genericRenderer = new GenericPanelRenderer();
        $this->assets = new ToolbarAssets();
    }

    public function addPanelRenderer(AbstractPanelRenderer&PanelRendererInterface $renderer): void
    {
        $this->panelRenderers[$renderer->getName()] = $renderer;
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

        // Build badges
        $badges = '';
        foreach ($collectors as $collector) {
            $badges .= $this->renderBadge($collector);
        }
        $perfBadge = $this->performanceRenderer->renderBadge($profile);
        $badges = $perfBadge . $badges;

        // Build sidebar and content panels
        $collectorNames = array_keys($collectors);
        $sidebarHtml = $this->renderSidebar($collectorNames, $collectors);
        $contentPanels = $this->renderContentPanels($profile, $collectors);

        $requestInfo = $this->esc($profile->getMethod()) . ' ' . $this->esc((string) $profile->getStatusCode());
        $totalTime = $this->formatMs($profile->getTime());

        $css = $this->assets->renderCss();
        $js = $this->assets->renderJs();
        $closeIcon = ToolbarIcons::svg('close', 14);

        return <<<HTML
        <div id="wppack-debug">
        <style>{$css}</style>
        <div class="wpd-overlay" style="display:none">
            <div class="wpd-sidebar">
                {$sidebarHtml}
            </div>
            <div class="wpd-content">
                <div class="wpd-content-header">
                    <span class="wpd-panel-title">Performance</span>
                    <button class="wpd-panel-close" data-action="close-panel" title="Close">{$closeIcon}</button>
                </div>
                <div class="wpd-content-body">
                    {$contentPanels}
                </div>
            </div>
        </div>
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
            <button class="wpd-close-btn" data-action="minimize" title="Close toolbar">{$closeIcon}</button>
        </div>
        <script>{$js}</script>
        </div>
        HTML;
    }

    /**
     * @param list<string> $collectorNames
     * @param array<string, DataCollectorInterface> $collectors
     */
    private function renderSidebar(array $collectorNames, array $collectors): string
    {
        $knownNames = array_merge(...self::SIDEBAR_GROUPS);
        $html = '';
        $groupIndex = 0;

        foreach (self::SIDEBAR_GROUPS as $group) {
            $visibleItems = [];
            foreach ($group as $name) {
                if ($name === 'performance' || \in_array($name, $collectorNames, true)) {
                    $visibleItems[] = $name;
                }
            }

            if ($visibleItems === []) {
                continue;
            }

            if ($groupIndex > 0) {
                $html .= '<div class="wpd-sidebar-divider"></div>';
            }

            foreach ($visibleItems as $key) {
                $icon = ToolbarIcons::svg($key, 18);
                $label = $this->getPanelLabel($key, $collectors);
                $html .= '<button class="wpd-sidebar-item" data-panel="' . $this->esc($key) . '">'
                    . '<span class="wpd-sidebar-icon">' . $icon . '</span>'
                    . '<span class="wpd-sidebar-label">' . $this->esc($label) . '</span>'
                    . '</button>';
            }

            $groupIndex++;
        }

        // Collectors not in any sidebar group
        $unknownNames = array_diff($collectorNames, $knownNames);
        if ($unknownNames !== []) {
            if ($groupIndex > 0) {
                $html .= '<div class="wpd-sidebar-divider"></div>';
            }
            foreach ($unknownNames as $key) {
                $icon = ToolbarIcons::svg($key, 18);
                $label = $collectors[$key]->getLabel();
                $html .= '<button class="wpd-sidebar-item" data-panel="' . $this->esc($key) . '">'
                    . '<span class="wpd-sidebar-icon">' . $icon . '</span>'
                    . '<span class="wpd-sidebar-label">' . $this->esc($label) . '</span>'
                    . '</button>';
            }
        }

        return $html;
    }

    /**
     * @param array<string, DataCollectorInterface> $collectors
     */
    private function renderContentPanels(Profile $profile, array $collectors): string
    {
        $html = '';

        // Build ordered panel list from sidebar groups
        $knownNames = array_merge(...self::SIDEBAR_GROUPS);
        $orderedNames = [];
        foreach ($knownNames as $name) {
            if ($name === 'performance' || isset($collectors[$name])) {
                $orderedNames[] = $name;
            }
        }
        // Add unknown collectors at the end
        foreach ($collectors as $name => $collector) {
            if (!\in_array($name, $orderedNames, true)) {
                $orderedNames[] = $name;
            }
        }

        foreach ($orderedNames as $key) {
            $display = ($key === 'performance') ? '' : ' style="display:none"';

            if ($key === 'performance') {
                $content = $this->performanceRenderer->renderContent($profile);
            } else {
                $content = $this->renderPanelContent($collectors[$key]);
            }

            $html .= '<div class="wpd-panel-content" id="wpd-pc-' . $this->esc($key) . '"' . $display . '>'
                . $content . '</div>';
        }

        return $html;
    }

    private function renderBadge(DataCollectorInterface $collector): string
    {
        $name = $this->esc($collector->getName());
        $label = $this->esc($collector->getLabel());
        $value = $this->esc($collector->getBadgeValue());
        $colorKey = $collector->getBadgeColor();
        $color = self::BADGE_COLORS[$colorKey] ?? self::BADGE_COLORS['default'];
        $icon = ToolbarIcons::svg($collector->getName());

        return <<<HTML
        <button class="wpd-badge" data-panel="{$name}" title="{$label}">
            <span class="wpd-badge-icon">{$icon}</span>
            <span class="wpd-badge-value" style="color:{$color}">{$value}</span>
        </button>
        HTML;
    }

    private function renderPanelContent(DataCollectorInterface $collector): string
    {
        $renderer = $this->panelRenderers[$collector->getName()] ?? $this->genericRenderer;

        return $renderer->render($collector->getData());
    }

    /**
     * @param array<string, DataCollectorInterface> $collectors
     */
    private function getPanelLabel(string $name, array $collectors): string
    {
        if ($name === 'performance') {
            return 'Performance';
        }

        return $collectors[$name]->getLabel();
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
