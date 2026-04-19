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

namespace WPPack\Component\Debug\DataCollector;

use WPPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'theme', priority: 140)]
final class ThemeDataCollector extends AbstractDataCollector
{
    private float $setupStart = 0.0;

    private float $setupTime = 0.0;

    private float $renderStart = 0.0;

    private float $renderTime = 0.0;

    private string $templateFile = '';

    /** @var list<string> */
    private array $templateParts = [];

    /** @var list<string> */
    private array $bodyClasses = [];

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'theme';
    }

    public function getLabel(): string
    {
        return 'Theme';
    }

    public function captureSetupStart(): void
    {
        $this->setupStart = microtime(true);
    }

    public function captureSetupEnd(): void
    {
        if ($this->setupStart > 0) {
            $this->setupTime = (microtime(true) - $this->setupStart) * 1000;
        }
    }

    public function captureRenderStart(): void
    {
        $this->renderStart = microtime(true);
    }

    public function captureRenderEnd(): void
    {
        if ($this->renderStart > 0) {
            $this->renderTime = (microtime(true) - $this->renderStart) * 1000;
        }
    }

    /**
     * Capture the template file being used.
     */
    public function captureTemplateInclude(string $template): string
    {
        $this->templateFile = $template;

        return $template;
    }

    /**
     * Capture template part loading.
     */
    public function captureTemplatePart(string $slug): void
    {
        $this->templateParts[] = $slug;
    }

    /**
     * Capture body classes.
     *
     * @param list<string> $classes
     * @return list<string>
     */
    public function captureBodyClass(array $classes): array
    {
        $this->bodyClasses = $classes;

        return $classes;
    }

    public function collect(): void
    {
        $theme = wp_get_theme();
        $isChildTheme = is_child_theme();
        $isBlockTheme = wp_is_block_theme();

        // Build hook attribution for theme
        global $wp_filter;
        $themeHooks = $this->buildThemeHookAttribution($wp_filter ?? []);
        $hookTime = 0.0;
        $listenerCount = 0;
        foreach ($themeHooks as $hookInfo) {
            $hookTime += $hookInfo['time'];
            $listenerCount += $hookInfo['listeners'];
        }

        // Enqueued assets
        global $wp_styles, $wp_scripts;
        $enqueuedStyles = [];
        $enqueuedScripts = [];

        if (isset($wp_styles) && is_object($wp_styles) && isset($wp_styles->queue)) {
            $enqueuedStyles = $wp_styles->queue;
        }
        if (isset($wp_scripts) && is_object($wp_scripts) && isset($wp_scripts->queue)) {
            $enqueuedScripts = $wp_scripts->queue;
        }

        // Conditional tags
        $conditionalTags = [
            'is_single' => is_single(),
            'is_page' => is_page(),
            'is_archive' => is_archive(),
            'is_home' => is_home(),
            'is_front_page' => is_front_page(),
            'is_admin' => is_admin(),
            'is_search' => is_search(),
            'is_404' => is_404(),
        ];

        $this->data = [
            'exists' => $theme->exists(),
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'is_child_theme' => $isChildTheme,
            'child_theme' => $isChildTheme ? $theme->get_stylesheet() : '',
            'parent_theme' => $isChildTheme ? $theme->get_template() : '',
            'is_block_theme' => $isBlockTheme,
            'template_file' => $this->templateFile,
            'template_parts' => $this->templateParts,
            'body_classes' => $this->bodyClasses,
            'conditional_tags' => $conditionalTags,
            'enqueued_styles' => $enqueuedStyles,
            'enqueued_scripts' => $enqueuedScripts,
            'setup_time' => round($this->setupTime, 2),
            'render_time' => round($this->renderTime, 2),
            'hook_count' => count($themeHooks),
            'listener_count' => $listenerCount,
            'hook_time' => round($hookTime, 2),
            'hooks' => $themeHooks,
        ];
    }

    public function getIndicatorValue(): string
    {
        return '';
    }

    public function getIndicatorColor(): string
    {
        return 'default';
    }

    public function reset(): void
    {
        parent::reset();
        $this->setupStart = 0.0;
        $this->setupTime = 0.0;
        $this->renderStart = 0.0;
        $this->renderTime = 0.0;
        $this->templateFile = '';
        $this->templateParts = [];
        $this->bodyClasses = [];
    }

    /**
     * Build hook attribution for the current theme.
     *
     * @param array<string, mixed> $wpFilter
     * @return list<array{hook: string, listeners: int, time: float}>
     */
    private function buildThemeHookAttribution(array $wpFilter): array
    {
        $themeDir = ABSPATH . 'wp-content/themes';

        $hooks = [];

        foreach ($wpFilter as $hookName => $hookObj) {
            if (!is_object($hookObj) || !isset($hookObj->callbacks)) {
                continue;
            }

            $themeListeners = 0;

            foreach ($hookObj->callbacks as $priority => $funcs) {
                foreach ($funcs as $func) {
                    $fileName = $this->getCallbackFileName($func['function'] ?? null);
                    if ($fileName !== null && str_starts_with($fileName, $themeDir)) {
                        $themeListeners++;
                    }
                }
            }

            if ($themeListeners > 0) {
                $hooks[] = [
                    'hook' => $hookName,
                    'listeners' => $themeListeners,
                    'time' => 0.0,
                ];
            }
        }

        // Sort by listeners descending
        usort($hooks, static fn(array $a, array $b): int => $b['listeners'] <=> $a['listeners']);

        return $hooks;
    }

    private function getCallbackFileName(mixed $callback): ?string
    {
        try {
            if ($callback instanceof \Closure) {
                return (new \ReflectionFunction($callback))->getFileName() ?: null;
            }

            if (is_array($callback) && count($callback) === 2) {
                [$classOrObject, $method] = $callback;
                $className = is_object($classOrObject) ? $classOrObject::class : (string) $classOrObject;

                return (new \ReflectionMethod($className, (string) $method))->getFileName() ?: null;
            }

            if (is_string($callback) && function_exists($callback)) {
                return (new \ReflectionFunction($callback))->getFileName() ?: null;
            }
        } catch (\ReflectionException) {
            return null;
        }

        return null;
    }

    private function registerHooks(): void
    {
        add_action('setup_theme', [$this, 'captureSetupStart'], \PHP_INT_MIN, 0);
        add_action('after_setup_theme', [$this, 'captureSetupEnd'], \PHP_INT_MAX, 0);
        add_action('template_redirect', [$this, 'captureRenderStart'], \PHP_INT_MIN, 0);
        add_action('wp_footer', [$this, 'captureRenderEnd'], \PHP_INT_MAX, 0);
        add_action('get_template_part', [$this, 'captureTemplatePart'], 10, 1);
        add_filter('template_include', [$this, 'captureTemplateInclude'], \PHP_INT_MAX, 1);
        add_filter('body_class', [$this, 'captureBodyClass'], \PHP_INT_MAX, 1);
    }
}
