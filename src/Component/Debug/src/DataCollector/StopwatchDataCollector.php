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

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;
use WpPack\Component\Stopwatch\Stopwatch;

#[AsDataCollector(name: 'stopwatch', priority: 250)]
final class StopwatchDataCollector extends AbstractDataCollector
{
    private const FRONTEND_PHASES = [
        'muplugins_loaded',
        'plugins_loaded',
        'setup_theme',
        'after_setup_theme',
        'init',
        'wp_loaded',
        'wp',
        'template_redirect',
        'wp_head',
        'wp_footer',
    ];

    private const ADMIN_PHASES = [
        'muplugins_loaded',
        'plugins_loaded',
        'setup_theme',
        'after_setup_theme',
        'init',
        'wp_loaded',
        'admin_init',
        'admin_menu',
        'admin_enqueue_scripts',
        'admin_head',
        'admin_footer',
    ];

    /** @var array<string, float> */
    private array $phases = [];

    public function __construct(
        private readonly Stopwatch $stopwatch,
    ) {
        $this->registerHooks();

        // If constructed during a hook, start measuring the current phase
        $currentAction = current_action();
        if (\is_string($currentAction) && $currentAction !== '' && !$this->stopwatch->isStarted($currentAction)) {
            $this->markPhase($currentAction);
        }
    }

    public function getName(): string
    {
        return 'stopwatch';
    }

    public function getLabel(): string
    {
        return 'Stopwatch';
    }

    public function collect(): void
    {
        $requestTimeFloat = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $totalTime = (microtime(true) - (float) $requestTimeFloat) * 1000;

        // Stop any phases that are still running
        $this->stopRunningPhases();

        // Compute hrtime origin (hrtime value at request start)
        $hrtimeOrigin = hrtime(true) / 1e6 - $totalTime;

        $events = [];
        foreach ($this->stopwatch->getEvents() as $name => $event) {
            $events[$name] = [
                'name' => $event->name,
                'category' => $event->category,
                'duration' => $event->duration,
                'memory' => $event->memory,
                'start_time' => round($event->startTime - $hrtimeOrigin, 2),
                'end_time' => round($event->endTime - $hrtimeOrigin, 2),
            ];
        }

        $this->data = [
            'total_time' => round($totalTime, 2),
            'request_time_float' => (float) $requestTimeFloat,
            'events' => $events,
            'phases' => $this->phases,
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

    /**
     * Hook callback for muplugins_loaded.
     */
    public function onMuPluginsLoaded(): void
    {
        $this->markPhase('muplugins_loaded');
    }

    /**
     * Hook callback for plugins_loaded.
     */
    public function onPluginsLoaded(): void
    {
        $this->markPhase('plugins_loaded');
    }

    /**
     * Hook callback for setup_theme.
     */
    public function onSetupTheme(): void
    {
        $this->markPhase('setup_theme');
    }

    /**
     * Hook callback for after_setup_theme.
     */
    public function onAfterSetupTheme(): void
    {
        $this->markPhase('after_setup_theme');
    }

    /**
     * Hook callback for init.
     */
    public function onInit(): void
    {
        $this->markPhase('init');
    }

    /**
     * Hook callback for wp_loaded.
     */
    public function onWpLoaded(): void
    {
        $this->markPhase('wp_loaded');
    }

    /**
     * Hook callback for wp.
     */
    public function onWp(): void
    {
        $this->markPhase('wp');
    }

    /**
     * Hook callback for template_redirect.
     */
    public function onTemplateRedirect(): void
    {
        $this->markPhase('template_redirect');
    }

    /**
     * Hook callback for wp_head.
     */
    public function onWpHead(): void
    {
        $this->markPhase('wp_head');
    }

    /**
     * Hook callback for wp_footer.
     */
    public function onWpFooter(): void
    {
        $this->markPhase('wp_footer');
    }

    /**
     * Hook callback for admin_init.
     */
    public function onAdminInit(): void
    {
        $this->markPhase('admin_init');
    }

    /**
     * Hook callback for admin_menu.
     */
    public function onAdminMenu(): void
    {
        $this->markPhase('admin_menu');
    }

    /**
     * Hook callback for admin_enqueue_scripts.
     */
    public function onAdminEnqueueScripts(): void
    {
        $this->markPhase('admin_enqueue_scripts');
    }

    /**
     * Hook callback for admin_head.
     */
    public function onAdminHead(): void
    {
        $this->markPhase('admin_head');
    }

    /**
     * Hook callback for admin_footer.
     */
    public function onAdminFooter(): void
    {
        $this->markPhase('admin_footer');
    }

    public function reset(): void
    {
        parent::reset();
        $this->phases = [];
        $this->stopwatch->reset();
    }

    private function markPhase(string $name): void
    {
        $requestTimeFloat = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $this->phases[$name] = round((microtime(true) - (float) $requestTimeFloat) * 1000, 2);
        $this->stopwatch->start($name, 'wordpress');
    }

    private function endPhase(string $name): void
    {
        if ($this->stopwatch->isStarted($name)) {
            $this->stopwatch->stop($name);
        }
    }

    private function stopRunningPhases(): void
    {
        $allPhases = array_unique([...self::FRONTEND_PHASES, ...self::ADMIN_PHASES]);

        foreach ($allPhases as $phase) {
            if ($this->stopwatch->isStarted($phase)) {
                $this->stopwatch->stop($phase);
            }
        }
    }

    private function registerHooks(): void
    {
        // Common phases (start at PHP_INT_MIN)
        add_action('muplugins_loaded', [$this, 'onMuPluginsLoaded'], PHP_INT_MIN);
        add_action('plugins_loaded', [$this, 'onPluginsLoaded'], PHP_INT_MIN);
        add_action('setup_theme', [$this, 'onSetupTheme'], PHP_INT_MIN);
        add_action('after_setup_theme', [$this, 'onAfterSetupTheme'], PHP_INT_MIN);
        add_action('init', [$this, 'onInit'], PHP_INT_MIN);
        add_action('wp_loaded', [$this, 'onWpLoaded'], PHP_INT_MIN);

        // Frontend phases (start at PHP_INT_MIN)
        add_action('wp', [$this, 'onWp'], PHP_INT_MIN);
        add_action('template_redirect', [$this, 'onTemplateRedirect'], PHP_INT_MIN);
        add_action('wp_head', [$this, 'onWpHead'], PHP_INT_MIN);
        add_action('wp_footer', [$this, 'onWpFooter'], PHP_INT_MIN);

        // Admin phases (start at PHP_INT_MIN)
        add_action('admin_init', [$this, 'onAdminInit'], PHP_INT_MIN);
        add_action('admin_menu', [$this, 'onAdminMenu'], PHP_INT_MIN);
        add_action('admin_enqueue_scripts', [$this, 'onAdminEnqueueScripts'], PHP_INT_MIN);
        add_action('admin_head', [$this, 'onAdminHead'], PHP_INT_MIN);
        add_action('admin_footer', [$this, 'onAdminFooter'], PHP_INT_MIN);

        // End callbacks (stop at PHP_INT_MAX)
        $allHooks = array_unique([...self::FRONTEND_PHASES, ...self::ADMIN_PHASES]);
        foreach ($allHooks as $hook) {
            add_action($hook, fn() => $this->endPhase($hook), PHP_INT_MAX);
        }
    }
}
