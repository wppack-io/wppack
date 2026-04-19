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

namespace WPPack\Plugin\RedisCachePlugin;

use Composer\InstalledVersions;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WPPack\Component\Kernel\AbstractPlugin;
use WPPack\Component\Kernel\Attribute\TextDomain;
use WPPack\Component\Kernel\ManagesDropin;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Plugin\RedisCachePlugin\Admin\RedisCacheSettingsController;
use WPPack\Plugin\RedisCachePlugin\Admin\RedisCacheSettingsPage;
use WPPack\Plugin\RedisCachePlugin\Configuration\RedisCacheConfiguration;
use WPPack\Plugin\RedisCachePlugin\DependencyInjection\RedisCachePluginServiceProvider;

#[TextDomain(domain: 'wppack-cache')]
final class RedisCachePlugin extends AbstractPlugin
{
    use ManagesDropin;

    private readonly RedisCachePluginServiceProvider $serviceProvider;

    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
        $this->serviceProvider = new RedisCachePluginServiceProvider();
    }

    public function register(ContainerBuilder $builder): void
    {
        $this->serviceProvider->registerAdmin($builder);

        if (!RedisCacheConfiguration::hasConfiguration()) {
            return;
        }

        $this->serviceProvider->register($builder);
    }

    /**
     * @return CompilerPassInterface[]
     */
    public function getCompilerPasses(): array
    {
        return [
            new RegisterHookSubscribersPass(),
        ];
    }

    public function boot(Container $container): void
    {
        /** @var AdminPageRegistry $pageRegistry */
        $pageRegistry = $container->get(AdminPageRegistry::class);
        /** @var RedisCacheSettingsPage $settingsPage */
        $settingsPage = $container->get(RedisCacheSettingsPage::class);
        $settingsPage->setPluginFile($this->getFile());
        $pageRegistry->register($settingsPage, $this->isNetworkActivated());

        /** @var RestRegistry $restRegistry */
        $restRegistry = $container->get(RestRegistry::class);
        $restRegistry->register($container->get(RedisCacheSettingsController::class));
    }

    public function onActivate(): void
    {
        $this->installDropin();
        wp_cache_flush();
    }

    public function onDeactivate(): void
    {
        wp_cache_flush();
        $this->uninstallDropin();
    }

    private function getDropinFilename(): string
    {
        return 'object-cache.php';
    }

    private function getDropinSignature(): string
    {
        return 'WPPack Object Cache Drop-in';
    }

    private function resolveDropinSource(): ?string
    {
        if (class_exists(InstalledVersions::class)) {
            $installPath = InstalledVersions::getInstallPath('wppack/cache');

            if ($installPath !== null) {
                return $installPath . '/drop-in/object-cache.php';
            }
        }

        // Monorepo fallback (replace in root composer.json)
        $monorepoPath = dirname(__DIR__, 3) . '/Component/Cache/drop-in/object-cache.php';

        return file_exists($monorepoPath) ? $monorepoPath : null;
    }
}
