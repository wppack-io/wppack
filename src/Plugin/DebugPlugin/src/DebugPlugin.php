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
use WpPack\Component\Debug\ErrorHandler\RedirectHandler;
use WpPack\Component\Debug\ErrorHandler\WpDieHandler;
use WpPack\Component\Debug\Toolbar\ToolbarSubscriber;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Kernel\ManagesDropin;
use WpPack\Component\Logger\DependencyInjection\RegisterLoggerPass;
use WpPack\Plugin\DebugPlugin\DependencyInjection\DebugPluginServiceProvider;

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
        return 'WpPack Fatal Error Handler Drop-in';
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
