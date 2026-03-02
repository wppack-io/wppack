<?php

declare(strict_types=1);

namespace WpPack\Component\Kernel;

use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Kernel\Exception\KernelAlreadyBootedException;

class Kernel
{
    private static ?self $instance = null;

    private readonly string $environment;

    private readonly bool $debug;

    /** @var PluginInterface[] */
    private array $plugins = [];

    /** @var ThemeInterface[] */
    private array $themes = [];

    private bool $booted = false;

    private ?Container $container = null;

    public function __construct(
        ?string $environment = null,
        ?bool $debug = null,
        bool $autoBoot = true,
    ) {
        $this->environment = $environment
            ?? (\function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production');
        $this->debug = $debug ?? (\defined('WP_DEBUG') && WP_DEBUG);

        if ($autoBoot && \function_exists('add_action')) {
            add_action('init', [self::class, 'autoBoot'], 0);
        }
    }

    public static function registerPlugin(PluginInterface $plugin): void
    {
        self::getInstance()->addPlugin($plugin);

        $pluginFile = $plugin->getPluginFile();
        if (\function_exists('register_activation_hook')) {
            register_activation_hook($pluginFile, [$plugin, 'onActivate']);
        }
        if (\function_exists('register_deactivation_hook')) {
            register_deactivation_hook($pluginFile, [$plugin, 'onDeactivate']);
        }
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
        return $this->environment;
    }

    public function isDebug(): bool
    {
        return $this->debug;
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

        return $this;
    }

    public function boot(): Container
    {
        if ($this->booted) {
            throw new KernelAlreadyBootedException();
        }

        $builder = new ContainerBuilder();

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
        $this->container = $builder->compile();

        // 4. Boot all plugins and themes
        foreach ($this->plugins as $plugin) {
            $plugin->boot($this->container);
        }

        foreach ($this->themes as $theme) {
            $theme->boot($this->container);
        }

        $this->booted = true;

        return $this->container;
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
}
