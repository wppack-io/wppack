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
use WpPack\Component\Templating\PhpRenderer;

final class ToolbarRenderer
{
    /** Indicator display order in the toolbar bar. */
    private const INDICATOR_ORDER = [
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

    private readonly ToolbarAssets $assets;

    private ?PhpRenderer $lazyPhpRenderer = null;

    public function __construct(
        private readonly Profile $profile,
        private readonly ?PhpRenderer $phpRenderer = null,
    ) {
        $this->assets = new ToolbarAssets();
    }

    public function getPhpRenderer(): PhpRenderer
    {
        if ($this->phpRenderer !== null) {
            return $this->phpRenderer;
        }

        return $this->lazyPhpRenderer ??= new PhpRenderer([
            dirname(__DIR__, 2) . '/templates',
        ]);
    }

    public function addPanelRenderer(AbstractPanelRenderer&RendererInterface $renderer): void
    {
        $this->panelRenderers[$renderer->getName()] = $renderer;
    }

    public function render(): string
    {
        $collectors = $this->profile->getCollectors();

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

        // Build ordered indicators (wordpress group first)
        $indicators = $this->renderOrderedIndicators($collectors);

        // Determine default panel (wordpress if available, else performance)
        $defaultPanel = isset($collectors['wordpress']) ? 'wordpress' : 'performance';

        // Build sidebar and content panels
        $collectorNames = array_keys($collectors);
        $sidebarHtml = $this->renderSidebar($collectorNames, $collectors);
        $contentPanels = $this->renderContentPanels($collectors, $defaultPanel);

        // Logo & version (delegated to WordPressPanelRenderer)
        $wpMiniIcon = ToolbarIcons::svg('wordpress', 16);
        $wpIndicatorHtml = isset($this->panelRenderers['wordpress'])
            ? $this->panelRenderers['wordpress']->renderIndicator()
            : '';

        // Environment info (delegated to EnvironmentPanelRenderer)
        $envHtml = isset($this->panelRenderers['environment'])
            ? $this->panelRenderers['environment']->renderIndicator()
            : '';

        // Default panel title
        $defaultTitle = $this->esc($this->getPanelLabel($defaultPanel, $collectors));

        $css = $this->assets->renderCss();
        $js = $this->assets->renderJs();
        $closeIcon = ToolbarIcons::svg('close', 14);

        return $this->getPhpRenderer()->render('toolbar/layout', [
            'css' => $css,
            'js' => $js,
            'sidebarHtml' => $sidebarHtml,
            'contentPanels' => $contentPanels,
            'defaultTitle' => $defaultTitle,
            'indicators' => $indicators,
            'wpIndicatorHtml' => $wpIndicatorHtml,
            'envHtml' => $envHtml,
            'wpMiniIcon' => $wpMiniIcon,
            'closeIcon' => $closeIcon,
        ]);
    }

    /**
     * @param list<string> $collectorNames
     * @param array<string, DataCollectorInterface> $collectors
     */
    private function renderSidebar(array $collectorNames, array $collectors): string
    {
        $knownNames = array_merge(...self::SIDEBAR_GROUPS);

        // Build visible groups
        $groups = [];
        foreach (self::SIDEBAR_GROUPS as $group) {
            $visibleItems = [];
            foreach ($group as $name) {
                if (\in_array($name, $collectorNames, true) || isset($this->panelRenderers[$name])) {
                    $visibleItems[] = $name;
                }
            }
            $groups[] = $visibleItems;
        }

        $unknownNames = array_values(array_diff($collectorNames, $knownNames));

        // Build icon and label maps
        $allNames = array_merge($collectorNames, array_keys($this->panelRenderers));
        $iconMap = [];
        $labelMap = [];
        foreach (array_unique($allNames) as $key) {
            $iconMap[$key] = ToolbarIcons::svg($key, 18);
            $labelMap[$key] = $this->getPanelLabel($key, $collectors);
        }

        return $this->getPhpRenderer()->render('toolbar/sidebar', [
            'groups' => $groups,
            'unknownNames' => $unknownNames,
            'iconMap' => $iconMap,
            'labelMap' => $labelMap,
        ]);
    }

    /**
     * @param array<string, DataCollectorInterface> $collectors
     */
    private function renderContentPanels(array $collectors, string $defaultPanel): string
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
            $content = $this->renderPanelContent($key);

            $html .= '<div class="wpd-panel-content" id="wpd-pc-' . $this->esc($key) . '"' . $display . '>'
                . $content . '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, DataCollectorInterface> $collectors
     */
    private function renderOrderedIndicators(array $collectors): string
    {
        $indicators = '';
        $rendered = [];

        foreach (self::INDICATOR_ORDER as $name) {
            $renderer = $this->panelRenderers[$name] ?? null;
            if ($renderer !== null) {
                $indicators .= $renderer->renderIndicator();
                $rendered[] = $name;
            } elseif (isset($collectors[$name])) {
                $indicators .= $this->renderIndicator($collectors[$name]);
                $rendered[] = $name;
            }
        }

        // Unknown collectors at the end (skip wordpress/environment — rendered separately in the bar)
        foreach ($collectors as $name => $collector) {
            if ($name !== 'wordpress' && $name !== 'environment' && !\in_array($name, $rendered, true)) {
                $indicators .= $this->renderIndicator($collector);
            }
        }

        return $indicators;
    }

    private function renderIndicator(DataCollectorInterface $collector): string
    {
        $colorKey = $collector->getIndicatorColor();
        $indicatorColors = AbstractPanelRenderer::getIndicatorColors();

        return $this->getPhpRenderer()->render('toolbar/indicators/default', [
            'name' => $collector->getName(),
            'label' => $collector->getLabel(),
            'value' => $collector->getIndicatorValue(),
            'colorKey' => $colorKey,
            'colors' => $indicatorColors[$colorKey] ?? $indicatorColors['default'],
            'icon' => ToolbarIcons::svg($collector->getName()),
        ]);
    }

    private function renderPanelContent(string $name): string
    {
        $renderer = $this->panelRenderers[$name] ?? null;
        if ($renderer === null) {
            $generic = new GenericPanelRenderer($this->profile, collectorName: $name);

            return $generic->renderPanel();
        }

        return $renderer->renderPanel();
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
