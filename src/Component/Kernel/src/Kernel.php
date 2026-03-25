<?php

declare(strict_types=1);

namespace WpPack\Component\Kernel;

use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Kernel\Exception\KernelAlreadyBootedException;

class Kernel
{
    private static ?self $instance = null;

    private static ?Request $pendingRequest = null;

    private readonly string $environment;

    private readonly bool $debug;

    private ?Request $request = null;

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
        $this->environment = $environment ?? wp_get_environment_type();
        $this->debug = $debug ?? (\defined('WP_DEBUG') && WP_DEBUG);

        if ($autoBoot) {
            add_action('init', [self::class, 'autoBoot'], 0);
        }
    }

    /**
     * Pre-configures the Kernel with a Request before WordPress loads.
     *
     * Called by Handler to pass the Request that will be used during boot().
     * The Kernel is not instantiated here — WordPress functions are not
     * available yet. The pending Request is picked up by getInstance().
     */
    public static function create(?Request $request = null): void
    {
        self::$pendingRequest = $request;
    }

    public static function registerPlugin(PluginInterface $plugin): void
    {
        self::getInstance()->addPlugin($plugin);

        register_activation_hook($plugin->getFile(), [$plugin, 'onActivate']);
        register_deactivation_hook($plugin->getFile(), [$plugin, 'onDeactivate']);
    }

    public static function registerTheme(ThemeInterface $theme): void
    {
        self::getInstance()->addTheme($theme);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        if (self::$pendingRequest !== null) {
            self::$instance->request = self::$pendingRequest;
            self::$pendingRequest = null;
        }

        return self::$instance;
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
        remove_action('init', [self::class, 'autoBoot'], 0);

        self::$instance = null;
        self::$pendingRequest = null;
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
        $this->container = $builder->compile();

        // Set the synthetic Request service instance
        $builder->getSymfonyBuilder()->set(Request::class, $request);

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
