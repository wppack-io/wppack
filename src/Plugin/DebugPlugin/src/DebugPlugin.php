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

namespace WPPack\Plugin\DebugPlugin;

use Composer\InstalledVersions;
use WPPack\Component\Debug\DependencyInjection\InjectContainerSnapshotPass;
use WPPack\Component\Debug\DependencyInjection\RegisterDataCollectorsPass;
use WPPack\Component\Debug\DependencyInjection\RegisterPanelRenderersPass;
use WPPack\Component\Debug\ErrorHandler\ExceptionHandler;
use WPPack\Component\Debug\ErrorHandler\RedirectHandler;
use WPPack\Component\Debug\ErrorHandler\WpDieHandler;
use WPPack\Component\Debug\Toolbar\ToolbarSubscriber;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WPPack\Component\Kernel\AbstractPlugin;
use WPPack\Component\Kernel\ManagesDropin;
use WPPack\Component\Logger\DependencyInjection\RegisterLoggerPass;
use WPPack\Plugin\DebugPlugin\DependencyInjection\DebugPluginServiceProvider;

final class DebugPlugin extends AbstractPlugin
{
    use ManagesDropin;

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

        /** @var RedirectHandler $redirectHandler */
        $redirectHandler = $container->get(RedirectHandler::class);
        $redirectHandler->register();

        /** @var ExceptionHandler $exceptionHandler */
        $exceptionHandler = $container->get(ExceptionHandler::class);
        $exceptionHandler->register();

        /** @var WpDieHandler $wpDieHandler */
        $wpDieHandler = $container->get(WpDieHandler::class);
        $wpDieHandler->register();
    }

    public function onActivate(): void
    {
        $this->installDropin();
    }

    public function onDeactivate(): void
    {
        $this->uninstallDropin();
    }

    private function getDropinFilename(): string
    {
        return 'fatal-error-handler.php';
    }

    private function getDropinSignature(): string
    {
        return 'WPPack Fatal Error Handler Drop-in';
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
