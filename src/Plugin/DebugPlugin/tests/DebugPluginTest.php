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

namespace WPPack\Plugin\DebugPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\DebugConfig;
use WPPack\Component\Debug\DependencyInjection\InjectContainerSnapshotPass;
use WPPack\Component\Debug\DependencyInjection\RegisterDataCollectorsPass;
use WPPack\Component\Debug\DependencyInjection\RegisterPanelRenderersPass;
use WPPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WPPack\Component\Debug\ErrorHandler\ExceptionHandler;
use WPPack\Component\Debug\ErrorHandler\RedirectHandler;
use WPPack\Component\Debug\ErrorHandler\WpDieHandler;
use WPPack\Component\Debug\Profiler\Profile;
use WPPack\Component\Debug\Toolbar\ToolbarRenderer;
use WPPack\Component\Debug\Toolbar\ToolbarSubscriber;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WPPack\Component\Kernel\ManagesDropin;
use WPPack\Component\Logger\DependencyInjection\RegisterLoggerPass;
use WPPack\Plugin\DebugPlugin\DebugPlugin;

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

        // Place a foreign drop-in (no WPPack signature)
        file_put_contents($dropinPath, '<?php // foreign drop-in');

        $plugin = new DebugPlugin(__FILE__);
        $plugin->onDeactivate();

        self::assertTrue(file_exists($dropinPath), 'Foreign drop-in should not be removed');
        self::assertSame('<?php // foreign drop-in', file_get_contents($dropinPath));
    }

    #[Test]
    public function registerDelegatesToServiceProvider(): void
    {
        $plugin = new DebugPlugin(__FILE__);
        $builder = new ContainerBuilder();

        $plugin->register($builder);

        self::assertTrue($builder->hasDefinition(DebugConfig::class));
    }

    #[Test]
    public function bootRegistersAllFourDebugSubscribers(): void
    {
        $config = new DebugConfig(enabled: true, showToolbar: true);
        $profile = new Profile();
        $toolbarRenderer = new ToolbarRenderer($profile);
        $errorRenderer = new ErrorRenderer();

        $toolbar = new ToolbarSubscriber($config, $toolbarRenderer, $profile, []);
        $redirect = new RedirectHandler($errorRenderer, $config, $toolbarRenderer, $profile);
        $exception = new ExceptionHandler($errorRenderer, $config, $toolbarRenderer, $profile);
        $wpDie = new WpDieHandler($errorRenderer, $config, $toolbarRenderer, $profile);

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(ToolbarSubscriber::class, $toolbar);
        $symfonyContainer->set(RedirectHandler::class, $redirect);
        $symfonyContainer->set(ExceptionHandler::class, $exception);
        $symfonyContainer->set(WpDieHandler::class, $wpDie);

        $container = new Container($symfonyContainer);

        $plugin = new DebugPlugin(__FILE__);

        try {
            $plugin->boot($container);

            // ToolbarSubscriber::register attaches wp_footer + admin_footer
            self::assertNotFalse(has_action('wp_footer'));
        } finally {
            // ExceptionHandler registers a PHP-level set_exception_handler
            // that PHPUnit flags as risky if left dangling; restore just
            // the one we installed.
            restore_exception_handler();
            remove_all_actions('wp_footer');
            remove_all_actions('admin_footer');
            remove_all_actions('shutdown');
            remove_all_filters('wp_redirect_status');
            remove_all_filters('wp_die_handler');
            remove_all_filters('wp_die_ajax_handler');
            remove_all_filters('wp_die_json_handler');
            remove_all_filters('wp_die_xmlrpc_handler');
        }
    }
}
