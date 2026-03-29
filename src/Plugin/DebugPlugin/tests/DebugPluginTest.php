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

namespace WpPack\Plugin\DebugPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DependencyInjection\InjectContainerSnapshotPass;
use WpPack\Component\Debug\DependencyInjection\RegisterDataCollectorsPass;
use WpPack\Component\Debug\DependencyInjection\RegisterPanelRenderersPass;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\ManagesDropin;
use WpPack\Component\Logger\DependencyInjection\RegisterLoggerPass;
use WpPack\Plugin\DebugPlugin\DebugPlugin;

#[CoversClass(DebugPlugin::class)]
#[CoversClass(ManagesDropin::class)]
final class DebugPluginTest extends TestCase
{
    private string $contentDir;

    protected function setUp(): void
    {
        $this->contentDir = WP_CONTENT_DIR;
    }

    protected function tearDown(): void
    {
        $dropinPath = $this->contentDir . '/fatal-error-handler.php';

        if (is_link($dropinPath)) {
            unlink($dropinPath);
        } elseif (file_exists($dropinPath)) {
            unlink($dropinPath);
        }
    }

    #[Test]
    public function getCompilerPassesReturnsFivePasses(): void
    {
        $plugin = new DebugPlugin(__FILE__);
        $passes = $plugin->getCompilerPasses();

        self::assertCount(5, $passes);
    }

    #[Test]
    public function getCompilerPassesReturnsCompilerPassInstances(): void
    {
        $plugin = new DebugPlugin(__FILE__);
        $passes = $plugin->getCompilerPasses();

        foreach ($passes as $pass) {
            self::assertInstanceOf(CompilerPassInterface::class, $pass);
        }
    }

    #[Test]
    public function getCompilerPassesReturnsExpectedTypes(): void
    {
        $plugin = new DebugPlugin(__FILE__);
        $passes = $plugin->getCompilerPasses();

        self::assertInstanceOf(RegisterLoggerPass::class, $passes[0]);
        self::assertInstanceOf(RegisterDataCollectorsPass::class, $passes[1]);
        self::assertInstanceOf(RegisterPanelRenderersPass::class, $passes[2]);
        self::assertInstanceOf(RegisterHookSubscribersPass::class, $passes[3]);
        self::assertInstanceOf(InjectContainerSnapshotPass::class, $passes[4]);
    }

    #[Test]
    public function onActivateInstallsDropin(): void
    {
        $plugin = new DebugPlugin(__FILE__);
        $dropinPath = $this->contentDir . '/fatal-error-handler.php';

        // Ensure clean state
        if (file_exists($dropinPath) || is_link($dropinPath)) {
            unlink($dropinPath);
        }

        $plugin->onActivate();

        self::assertTrue(
            file_exists($dropinPath) || is_link($dropinPath),
            'Drop-in file should be installed after activation',
        );
    }

    #[Test]
    public function onActivateDoesNotOverwriteExistingDropin(): void
    {
        $dropinPath = $this->contentDir . '/fatal-error-handler.php';

        // Place a custom drop-in
        file_put_contents($dropinPath, '<?php // custom drop-in');
        $originalContent = file_get_contents($dropinPath);

        $plugin = new DebugPlugin(__FILE__);
        $plugin->onActivate();

        self::assertSame($originalContent, file_get_contents($dropinPath));
    }

    #[Test]
    public function onDeactivateRemovesOwnDropin(): void
    {
        $plugin = new DebugPlugin(__FILE__);
        $dropinPath = $this->contentDir . '/fatal-error-handler.php';

        // Ensure clean state, then install
        if (file_exists($dropinPath) || is_link($dropinPath)) {
            unlink($dropinPath);
        }

        $plugin->onActivate();

        self::assertTrue(
            file_exists($dropinPath) || is_link($dropinPath),
            'Drop-in should exist after activation',
        );

        $plugin->onDeactivate();

        self::assertFalse(
            file_exists($dropinPath) || is_link($dropinPath),
            'Drop-in should be removed after deactivation',
        );
    }

    #[Test]
    public function onDeactivateDoesNotRemoveForeignDropin(): void
    {
        $dropinPath = $this->contentDir . '/fatal-error-handler.php';

        // Place a foreign drop-in (no WpPack signature)
        file_put_contents($dropinPath, '<?php // foreign drop-in');

        $plugin = new DebugPlugin(__FILE__);
        $plugin->onDeactivate();

        self::assertTrue(file_exists($dropinPath), 'Foreign drop-in should not be removed');
        self::assertSame('<?php // foreign drop-in', file_get_contents($dropinPath));
    }
}
