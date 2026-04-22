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

namespace WPPack\Component\Kernel;

use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Kernel\Attribute\TextDomain;
use WPPack\Component\Kernel\Exception\KernelAlreadyBootedException;

class Kernel
{
    private static ?self $instance = null;

    private ?string $environment;

    private ?bool $debug;

    private ?Request $request = null;

    /** @var PluginInterface[] */
    private array $plugins = [];

    /** @var ThemeInterface[] */
    private array $themes = [];

    private bool $booted = false;

    private ?Container $container = null;

    private bool $autoBootPending = false;

    public function __construct(
        ?string $environment = null,
        ?bool $debug = null,
        bool $autoBoot = true,
    ) {
        $this->environment = $environment;
        $this->debug = $debug;

        if ($autoBoot) {
            if (\function_exists('add_action')) {
                add_action('init', [self::class, 'autoBoot'], 0);
            } else {
                $this->autoBootPending = true;
            }
        }
    }

    /**
     * Creates (or returns) the shared Kernel instance.
     *
     * Safe to call before WordPress loads — environment and debug
     * values are resolved lazily on first access.
     */
    public static function create(?Request $request = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        if ($request !== null) {
            self::$instance->request = $request;
        }

        return self::$instance;
    }

    public static function registerPlugin(PluginInterface $plugin): void
    {
        $kernel = self::getInstance();

        if (!$kernel->isBooted()) {
            $kernel->addPlugin($plugin);
        }

        register_activation_hook($plugin->getFile(), [$plugin, 'onActivate']);
        register_deactivation_hook($plugin->getFile(), [$plugin, 'onDeactivate']);
    }

    public static function registerTheme(ThemeInterface $theme): void
    {
        self::getInstance()->addTheme($theme);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function getEnvironment(): string
    {
        return $this->environment ??= wp_get_environment_type();
    }

    public function isDebug(): bool
    {
        return $this->debug ??= (\defined('WP_DEBUG') && WP_DEBUG);
    }

    /**
     * @internal
     */
    public static function autoBoot(): void
    {
        if (self::$instance !== null && !self::$instance->isBooted()) {
            self::$instance->boot();
        }
    }

    /**
     * @internal
     */
    public static function resetInstance(): void
    {
        if (\function_exists('remove_action')) {
            remove_action('init', [self::class, 'autoBoot'], 0);
        }

        self::$instance = null;
    }

    public function addPlugin(PluginInterface $plugin): self
    {
        if ($this->booted) {
            throw new KernelAlreadyBootedException();
        }

        if (!in_array($plugin, $this->plugins, true)) {
            $this->plugins[] = $plugin;
        }

        $this->flushAutoBootPending();

        return $this;
    }

    public function addTheme(ThemeInterface $theme): self
    {
        if ($this->booted) {
            throw new KernelAlreadyBootedException();
        }

        if (!in_array($theme, $this->themes, true)) {
            $this->themes[] = $theme;
        }

        $this->flushAutoBootPending();

        return $this;
    }

    private function flushAutoBootPending(): void
    {
        if ($this->autoBootPending) {
            $this->autoBootPending = false;
            add_action('init', [self::class, 'autoBoot'], 0);
        }
    }

    public function boot(): Container
    {
        if ($this->booted) {
            throw new KernelAlreadyBootedException();
        }

        $builder = new ContainerBuilder();

        // Register Request as a synthetic service
        $request = $this->request ?? Request::createFromGlobals();
        $builder->getSymfonyBuilder()
            ->register(Request::class, Request::class)
            ->setSynthetic(true)
            ->setPublic(true);

        // 1. Register all plugins, then themes
        foreach ($this->plugins as $plugin) {
            $plugin->register($builder);
        }

        foreach ($this->themes as $theme) {
            $theme->register($builder);
        }

        // 2. Collect and add compiler passes from plugins and themes
        foreach ($this->plugins as $plugin) {
            foreach ($plugin->getCompilerPasses() as $pass) {
                $builder->addCompilerPass($pass);
            }
        }

        foreach ($this->themes as $theme) {
            foreach ($theme->getCompilerPasses() as $pass) {
                $builder->addCompilerPass($pass);
            }
        }

        // 3. Compile the container
        $container = $builder->compile();
        $this->container = $container;

        // Set the synthetic Request service instance
        $builder->getSymfonyBuilder()->set(Request::class, $request);

        // 4. Boot all plugins and themes
        foreach ($this->plugins as $plugin) {
            $this->loadTextDomains($plugin);
            $plugin->boot($container);
        }

        foreach ($this->themes as $theme) {
            $theme->boot($container);
        }

        $this->booted = true;

        return $container;
    }

    public function getContainer(): Container
    {
        if (!$this->booted || $this->container === null) {
            throw new \LogicException('Cannot get container before booting the kernel.');
        }

        return $this->container;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * @return PluginInterface[]
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * @return ThemeInterface[]
     */
    public function getThemes(): array
    {
        return $this->themes;
    }

    private function loadTextDomains(PluginInterface $plugin): void
    {
        $ref = new \ReflectionClass($plugin);
        foreach ($ref->getAttributes(TextDomain::class) as $attr) {
            $td = $attr->newInstance();
            $dir = \dirname(plugin_basename($plugin->getFile()));
            load_plugin_textdomain($td->domain, false, $dir . '/' . $td->path);
        }
    }
}
