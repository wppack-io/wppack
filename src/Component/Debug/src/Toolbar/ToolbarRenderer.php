<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar;

use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\Panel\AbstractPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\GenericPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RendererInterface;
use WpPack\Component\Debug\Toolbar\Panel\ToolbarAssets;
use WpPack\Component\Debug\Toolbar\Panel\ToolbarIcons;

final class ToolbarRenderer
{
    /** Badge display order in the toolbar bar. */
    private const BADGE_ORDER = [
        'plugin', 'theme',
        'performance',
        'request', 'router', 'rest', 'ajax', 'http_client',
        'stopwatch', 'memory', 'database', 'cache',
        'event', 'security', 'logger', 'container',
        'asset', 'widget', 'shortcode', 'admin',
        'mail', 'scheduler', 'translation', 'feed',
        'dump',
    ];

    /** Sidebar panel order, grouped by category. */
    private const SIDEBAR_GROUPS = [
        ['wordpress', 'plugin', 'theme'],
        ['performance'],
        ['request', 'router', 'rest', 'ajax', 'http_client'],
        ['stopwatch', 'memory', 'database', 'cache'],
        ['event', 'security', 'logger', 'container'],
        ['asset', 'widget', 'shortcode', 'admin'],
        ['mail', 'scheduler', 'translation', 'feed'],
        ['environment'],
        ['dump'],
    ];

    /** @var array<string, AbstractPanelRenderer&RendererInterface> */
    private array $panelRenderers = [];

    private readonly GenericPanelRenderer $genericRenderer;

    private readonly ToolbarAssets $assets;

    public function __construct()
    {
        $this->genericRenderer = new GenericPanelRenderer();
        $this->assets = new ToolbarAssets();
    }

    public function addPanelRenderer(AbstractPanelRenderer&RendererInterface $renderer): void
    {
        $this->panelRenderers[$renderer->getName()] = $renderer;
    }

    public function render(Profile $profile): string
    {
        $collectors = $profile->getCollectors();

        // Extract request_time_float for relative time display across panels
        $requestTimeFloat = 0.0;
        if (isset($collectors['stopwatch'])) {
            $timeData = $collectors['stopwatch']->getData();
            $requestTimeFloat = (float) ($timeData['request_time_float'] ?? 0.0);
        }

        // Propagate request_time_float to all panel renderers
        foreach ($this->panelRenderers as $renderer) {
            $renderer->setRequestTimeFloat($requestTimeFloat);
        }
        $this->genericRenderer->setRequestTimeFloat($requestTimeFloat);

        // Build ordered badges (wordpress group first)
        $badges = $this->renderOrderedBadges($profile, $collectors);

        // Determine default panel (wordpress if available, else performance)
        $defaultPanel = isset($collectors['wordpress']) ? 'wordpress' : 'performance';

        // Build sidebar and content panels
        $collectorNames = array_keys($collectors);
        $sidebarHtml = $this->renderSidebar($collectorNames, $collectors);
        $contentPanels = $this->renderContentPanels($profile, $collectors, $defaultPanel);

        // Logo & version (delegated to WordPressPanelRenderer)
        $wpMiniIcon = ToolbarIcons::svg('wordpress', 16);
        $wpBadgeHtml = isset($this->panelRenderers['wordpress'])
            ? $this->panelRenderers['wordpress']->renderBadge($profile)
            : '';

        // Environment info (delegated to EnvironmentPanelRenderer)
        $envHtml = isset($this->panelRenderers['environment'])
            ? $this->panelRenderers['environment']->renderBadge($profile)
            : '';

        // Default panel title
        $defaultTitle = $this->esc($this->getPanelLabel($defaultPanel, $collectors));

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
                    <span class="wpd-panel-title">{$defaultTitle}</span>
                    <button class="wpd-panel-close" data-action="close-panel" title="Close">{$closeIcon}</button>
                </div>
                <div class="wpd-content-body">
                    {$contentPanels}
                </div>
            </div>
        </div>
        <div class="wpd-mini" title="Show WpPack Debug Toolbar">
            {$wpMiniIcon}
        </div>
        <div class="wpd-bar">
            {$wpBadgeHtml}
            <div class="wpd-bar-badges-wrap">
                <div class="wpd-bar-badges">
                    {$badges}
                </div>
            </div>
            {$envHtml}
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
                if (\in_array($name, $collectorNames, true) || isset($this->panelRenderers[$name])) {
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
    private function renderContentPanels(Profile $profile, array $collectors, string $defaultPanel): string
    {
        $html = '';

        // Build ordered panel list from sidebar groups
        $knownNames = array_merge(...self::SIDEBAR_GROUPS);
        $orderedNames = [];
        foreach ($knownNames as $name) {
            if (isset($collectors[$name]) || isset($this->panelRenderers[$name])) {
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
            $display = ($key === $defaultPanel) ? '' : ' style="display:none"';
            $content = $this->renderPanelContent($profile, $key);

            $html .= '<div class="wpd-panel-content" id="wpd-pc-' . $this->esc($key) . '"' . $display . '>'
                . $content . '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, DataCollectorInterface> $collectors
     */
    private function renderOrderedBadges(Profile $profile, array $collectors): string
    {
        $badges = '';
        $rendered = [];

        foreach (self::BADGE_ORDER as $name) {
            $renderer = $this->panelRenderers[$name] ?? null;
            if ($renderer !== null) {
                $badges .= $renderer->renderBadge($profile);
                $rendered[] = $name;
            } elseif (isset($collectors[$name])) {
                $badges .= $this->renderBadge($collectors[$name]);
                $rendered[] = $name;
            }
        }

        // Unknown collectors at the end (skip wordpress — handled by logo button)
        foreach ($collectors as $name => $collector) {
            if ($name !== 'wordpress' && !\in_array($name, $rendered, true)) {
                $badges .= $this->renderBadge($collector);
            }
        }

        return $badges;
    }

    private function renderBadge(DataCollectorInterface $collector): string
    {
        $name = $this->esc($collector->getName());
        $label = $this->esc($collector->getLabel());
        $value = $collector->getBadgeValue();
        $colorKey = $collector->getBadgeColor();
        $badgeColors = AbstractPanelRenderer::getBadgeColors();
        $colors = $badgeColors[$colorKey] ?? $badgeColors['default'];
        $icon = ToolbarIcons::svg($collector->getName());

        $valueHtml = $value !== ''
            ? ' <span class="wpd-badge-value" style="color:' . $colors['fg'] . '">' . $this->esc($value) . '</span>'
            : '';

        $bgStyle = $colors['bg'] !== 'transparent' ? ' style="background:' . $colors['bg'] . '"' : '';
        $iconStyle = $colors['fg'] !== '#50575e' ? ' style="color:' . $colors['fg'] . '"' : '';

        $accentAttr = $colorKey !== 'default' ? ' data-accent="' . $colors['fg'] . '"' : '';

        return <<<HTML
        <button class="wpd-badge" data-panel="{$name}" data-tooltip="{$label}"{$bgStyle}{$accentAttr}>
            <span class="wpd-badge-icon"{$iconStyle}>{$icon}</span>{$valueHtml}
        </button>
        HTML;
    }

    private function renderPanelContent(Profile $profile, string $name): string
    {
        $renderer = $this->panelRenderers[$name] ?? null;
        if ($renderer === null) {
            $this->genericRenderer->setCollectorName($name);

            return $this->genericRenderer->renderPanel($profile);
        }

        return $renderer->renderPanel($profile);
    }

    /**
     * @param array<string, DataCollectorInterface> $collectors
     */
    private function getPanelLabel(string $name, array $collectors): string
    {
        if (isset($collectors[$name])) {
            return $collectors[$name]->getLabel();
        }

        // Panel renderers without a collector (e.g. performance)
        $renderer = $this->panelRenderers[$name] ?? null;
        if ($renderer !== null) {
            return ucfirst($name);
        }

        return ucfirst($name);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
