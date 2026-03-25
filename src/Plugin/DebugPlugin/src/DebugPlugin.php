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

namespace WpPack\Plugin\DebugPlugin;

use Composer\InstalledVersions;
use WpPack\Component\Debug\DependencyInjection\InjectContainerSnapshotPass;
use WpPack\Component\Debug\DependencyInjection\RegisterDataCollectorsPass;
use WpPack\Component\Debug\DependencyInjection\RegisterPanelRenderersPass;
use WpPack\Component\Debug\ErrorHandler\ExceptionHandler;
use WpPack\Component\Debug\ErrorHandler\WpDieHandler;
use WpPack\Component\Debug\Toolbar\ToolbarSubscriber;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Logger\DependencyInjection\RegisterLoggerPass;
use WpPack\Plugin\DebugPlugin\DependencyInjection\DebugPluginServiceProvider;

final class DebugPlugin extends AbstractPlugin
{
    private const DROPIN_SIGNATURE = 'WpPack Fatal Error Handler Drop-in';

    private readonly DebugPluginServiceProvider $serviceProvider;

    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
        $this->serviceProvider = new DebugPluginServiceProvider();
    }

    public function register(ContainerBuilder $builder): void
    {
        $this->serviceProvider->register($builder);
    }

    /**
     * @return CompilerPassInterface[]
     */
    public function getCompilerPasses(): array
    {
        return [
            new RegisterLoggerPass(),
            new RegisterDataCollectorsPass(),
            new RegisterPanelRenderersPass(),
            new RegisterHookSubscribersPass(),
            new InjectContainerSnapshotPass(),
        ];
    }

    public function boot(Container $container): void
    {
        /** @var ToolbarSubscriber $toolbar */
        $toolbar = $container->get(ToolbarSubscriber::class);
        $toolbar->register();

        /** @var ExceptionHandler $exceptionHandler */
        $exceptionHandler = $container->get(ExceptionHandler::class);
        $exceptionHandler->register();

        /** @var WpDieHandler $wpDieHandler */
        $wpDieHandler = $container->get(WpDieHandler::class);
        $wpDieHandler->register();
    }

    public function onActivate(): void
    {
        $destination = WP_CONTENT_DIR . '/fatal-error-handler.php';

        if (file_exists($destination) || !is_writable(WP_CONTENT_DIR)) {
            return;
        }

        $source = $this->resolveDropinSource();

        if ($source === null || !file_exists($source)) {
            return;
        }

        copy($source, $destination);
    }

    public function onDeactivate(): void
    {
        $destination = WP_CONTENT_DIR . '/fatal-error-handler.php';

        if (!file_exists($destination) || !is_writable($destination)) {
            return;
        }

        $header = file_get_contents($destination, false, null, 0, 512);

        if ($header !== false && str_contains($header, self::DROPIN_SIGNATURE)) {
            unlink($destination);
        }
    }

    private function resolveDropinSource(): ?string
    {
        if (class_exists(InstalledVersions::class)) {
            $installPath = InstalledVersions::getInstallPath('wppack/debug');

            if ($installPath !== null) {
                return $installPath . '/drop-in/fatal-error-handler.php';
            }
        }

        // Monorepo fallback (replace in root composer.json)
        $monorepoPath = dirname(__DIR__, 3) . '/Component/Debug/drop-in/fatal-error-handler.php';

        return file_exists($monorepoPath) ? $monorepoPath : null;
    }
}
