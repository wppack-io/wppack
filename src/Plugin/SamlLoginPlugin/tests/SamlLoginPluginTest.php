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

namespace WpPack\Plugin\SamlLoginPlugin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Option\OptionManager;
use WpPack\Component\Routing\RouteRegistry;
use WpPack\Plugin\SamlLoginPlugin\SamlLoginPlugin;

final class SamlLoginPluginTest extends TestCase
{
    #[Test]
    public function onActivateFlushesRewriteRules(): void
    {
        $router = new RouteRegistry(optionManager: new OptionManager());

        $plugin = new SamlLoginPlugin(__FILE__);

        $reflection = new \ReflectionProperty($plugin, 'router');
        $reflection->setValue($plugin, $router);

        // flush_rewrite_rules() internally calls flush() which updates the rewrite_rules option
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

        $plugin = new SamlLoginPlugin(__FILE__);

        $reflection = new \ReflectionProperty($plugin, 'router');
        $reflection->setValue($plugin, $router);

        $plugin->onDeactivate();

        self::assertFalse(get_option('rewrite_rules'));
    }

    #[Test]
    public function onActivateDoesNothingWhenRouterIsNull(): void
    {
        $plugin = new SamlLoginPlugin(__FILE__);

        // Should not throw when router is null (boot() not called)
        $plugin->onActivate();

        self::assertTrue(true);
    }

    #[Test]
    public function onDeactivateDoesNothingWhenRouterIsNull(): void
    {
        $plugin = new SamlLoginPlugin(__FILE__);

        // Should not throw when router is null (boot() not called)
        $plugin->onDeactivate();

        self::assertTrue(true);
    }
}
