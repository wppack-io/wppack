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
        'green' => '#008a20',
        'yellow' => '#996800',
        'red' => '#cc1818',
        'default' => '#50575e',
    ];

    /** Badge display order in the toolbar bar. */
    private const BADGE_ORDER = [
        'plugin', 'theme',
        'performance',
        'request', 'router', 'time', 'memory', 'database', 'cache', 'http_client',
        'event', 'logger', 'mail', 'scheduler', 'translation', 'user',
        'dump',
    ];

    /** Sidebar panel order, grouped by category. */
    private const SIDEBAR_GROUPS = [
        ['wordpress', 'plugin', 'theme'],
        ['performance'],
        ['request', 'router', 'time', 'memory', 'database', 'cache', 'http_client'],
        ['event', 'logger', 'mail', 'scheduler', 'translation', 'user'],
        ['dump'],
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

        // Build ordered badges (wordpress group first)
        $badges = $this->renderOrderedBadges($profile, $collectors);

        // Determine default panel (wordpress if available, else performance)
        $defaultPanel = isset($collectors['wordpress']) ? 'wordpress' : 'performance';

        // Build sidebar and content panels
        $collectorNames = array_keys($collectors);
        $sidebarHtml = $this->renderSidebar($collectorNames, $collectors);
        $contentPanels = $this->renderContentPanels($profile, $collectors, $defaultPanel);

        // Logo & version (clickable — opens WordPress panel)
        $wpIcon = ToolbarIcons::svg('wordpress', 18);
        $wpMiniIcon = ToolbarIcons::svg('wordpress', 16);
        $wpVersion = $this->getWpVersion($collectors);
        $wpBtnContent = '<span class="wpd-bar-logo">' . $wpIcon . '</span>';
        if ($wpVersion !== '') {
            $wpBtnContent .= '<span class="wpd-bar-version">' . $this->esc($wpVersion) . '</span>';
        }

        // Environment info
        $envHtml = $this->renderEnvironmentInfo($collectors);

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
            <button class="wpd-bar-wp" data-panel="wordpress" title="WordPress">
                {$wpBtnContent}
            </button>
            <div class="wpd-bar-badges">
                {$badges}
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
    private function renderContentPanels(Profile $profile, array $collectors, string $defaultPanel): string
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
            $display = ($key === $defaultPanel) ? '' : ' style="display:none"';

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

    /**
     * @param array<string, DataCollectorInterface> $collectors
     */
    private function renderOrderedBadges(Profile $profile, array $collectors): string
    {
        $badges = '';
        $rendered = [];

        foreach (self::BADGE_ORDER as $name) {
            if ($name === 'performance') {
                $badges .= $this->performanceRenderer->renderBadge($profile);
                $rendered[] = 'performance';
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
        $color = self::BADGE_COLORS[$colorKey] ?? self::BADGE_COLORS['default'];
        $icon = ToolbarIcons::svg($collector->getName());

        $valueHtml = $value !== ''
            ? ' <span class="wpd-badge-value" style="color:' . $color . '">' . $this->esc($value) . '</span>'
            : '';

        return <<<HTML
        <button class="wpd-badge" data-panel="{$name}" data-tooltip="{$label}">
            <span class="wpd-badge-icon">{$icon}</span>{$valueHtml}
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
    private function renderEnvironmentInfo(array $collectors): string
    {
        $parts = [];
        $tooltipLines = [];

        // PHP version
        $parts[] = 'PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $tooltipLines[] = 'PHP ' . PHP_VERSION;

        // Server software
        if (isset($collectors['request'])) {
            $requestData = $collectors['request']->getData();
            $serverSoftware = (string) ($requestData['server_vars']['SERVER_SOFTWARE'] ?? '');
            if ($serverSoftware !== '' && preg_match('/^([a-zA-Z]+)/', $serverSoftware, $m)) {
                $parts[] = ucfirst(strtolower($m[1]));
                $tooltipLines[] = $serverSoftware;
            }
        }

        // Additional tooltip info
        if (isset($collectors['wordpress'])) {
            $wpData = $collectors['wordpress']->getData();
            $wpVersion = (string) ($wpData['wp_version'] ?? '');
            if ($wpVersion !== '') {
                $tooltipLines[] = 'WordPress ' . $wpVersion;
            }
            $envType = (string) ($wpData['environment_type'] ?? '');
            if ($envType !== '') {
                $tooltipLines[] = 'Env: ' . $envType;
            }
        }

        if (isset($collectors['memory'])) {
            $memData = $collectors['memory']->getData();
            $limit = (int) ($memData['limit'] ?? 0);
            if ($limit > 0) {
                $tooltipLines[] = 'Memory Limit: ' . $this->formatBytes($limit);
            }
        }

        $labelParts = '';
        foreach ($parts as $i => $part) {
            if ($i > 0) {
                $labelParts .= '<span class="wpd-env-sep"></span>';
            }
            $labelParts .= $this->esc($part);
        }
        $tooltipHtml = '';
        foreach ($tooltipLines as $line) {
            $tooltipHtml .= '<div>' . $this->esc($line) . '</div>';
        }

        return '<div class="wpd-bar-env">'
            . '<span class="wpd-env-label">' . $labelParts . '</span>'
            . '<div class="wpd-env-tooltip">' . $tooltipHtml . '</div>'
            . '</div>';
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

    /**
     * @param array<string, DataCollectorInterface> $collectors
     */
    private function getWpVersion(array $collectors): string
    {
        if (!isset($collectors['wordpress'])) {
            return '';
        }

        return (string) ($collectors['wordpress']->getData()['wp_version'] ?? '');
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return sprintf('%.1f GB', $bytes / 1073741824);
        }

        if ($bytes >= 1048576) {
            return sprintf('%d MB', (int) ($bytes / 1048576));
        }

        return sprintf('%d KB', (int) ($bytes / 1024));
    }
}
