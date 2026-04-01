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

namespace WpPack\Plugin\RedisCachePlugin;

use Composer\InstalledVersions;
use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Kernel\Attribute\TextDomain;
use WpPack\Component\Kernel\ManagesDropin;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Plugin\RedisCachePlugin\Admin\RedisCacheSettingsController;
use WpPack\Plugin\RedisCachePlugin\Admin\RedisCacheSettingsPage;
use WpPack\Plugin\RedisCachePlugin\Configuration\RedisCacheConfiguration;
use WpPack\Plugin\RedisCachePlugin\DependencyInjection\RedisCachePluginServiceProvider;

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
        $this->serviceProvider->registerMonitoring($builder);

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
        if (is_admin() || is_network_admin()) {
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
        return 'WpPack Object Cache Drop-in';
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
