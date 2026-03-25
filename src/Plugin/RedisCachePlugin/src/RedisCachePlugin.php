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
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Plugin\RedisCachePlugin\DependencyInjection\RedisCachePluginServiceProvider;

final class RedisCachePlugin extends AbstractPlugin
{
    private const DROPIN_SIGNATURE = 'WpPack Object Cache Drop-in';

    private readonly RedisCachePluginServiceProvider $serviceProvider;

    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
        $this->serviceProvider = new RedisCachePluginServiceProvider();
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
            new RegisterHookSubscribersPass(),
        ];
    }

    public function onActivate(): void
    {
        $destination = WP_CONTENT_DIR . '/object-cache.php';

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
        $destination = WP_CONTENT_DIR . '/object-cache.php';

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
