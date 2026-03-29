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

namespace WpPack\Plugin\OAuthLoginPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;
use WpPack\Component\Option\OptionManager;
use WpPack\Component\Routing\RouteRegistry;
use WpPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;
use WpPack\Plugin\OAuthLoginPlugin\OAuthLoginPlugin;

#[CoversClass(OAuthLoginPlugin::class)]
final class OAuthLoginPluginTest extends TestCase
{
    #[Test]
    public function getCompilerPassesReturnsCorrectPasses(): void
    {
        $plugin = new OAuthLoginPlugin(__FILE__);
        $passes = $plugin->getCompilerPasses();

        self::assertCount(2, $passes);
        self::assertInstanceOf(RegisterAuthenticatorsPass::class, $passes[0]);
        self::assertInstanceOf(RegisterEventListenersPass::class, $passes[1]);

        foreach ($passes as $pass) {
            self::assertInstanceOf(CompilerPassInterface::class, $pass);
        }
    }

    #[Test]
    public function onActivateFlushesRewriteRules(): void
    {
        $router = new RouteRegistry(optionManager: new OptionManager());

        $plugin = new OAuthLoginPlugin(__FILE__);

        $reflection = new \ReflectionProperty($plugin, 'router');
        $reflection->setValue($plugin, $router);

        $plugin->onActivate();

        // After flush, rewrite_rules option should exist (non-false)
        self::assertNotFalse(get_option('rewrite_rules'));
    }

    #[Test]
    public function onDeactivateInvalidatesRewriteRules(): void
    {
        // Ensure rewrite_rules option exists first
        update_option('rewrite_rules', ['dummy' => 'rule']);

        $router = new RouteRegistry(optionManager: new OptionManager());

        $plugin = new OAuthLoginPlugin(__FILE__);

        $reflection = new \ReflectionProperty($plugin, 'router');
        $reflection->setValue($plugin, $router);

        $plugin->onDeactivate();

        self::assertFalse(get_option('rewrite_rules'));
    }

    #[Test]
    public function onActivateDoesNothingWhenRouterIsNull(): void
    {
        $plugin = new OAuthLoginPlugin(__FILE__);

        // Should not throw when router is null (boot() not called)
        $plugin->onActivate();

        self::assertTrue(true);
    }

    #[Test]
    public function onDeactivateDoesNothingWhenRouterIsNull(): void
    {
        $plugin = new OAuthLoginPlugin(__FILE__);

        // Should not throw when router is null (boot() not called)
        $plugin->onDeactivate();

        self::assertTrue(true);
    }
}
