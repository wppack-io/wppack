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

namespace WpPack\Plugin\ScimPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;
use WpPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WpPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;
use WpPack\Plugin\ScimPlugin\ScimPlugin;

#[CoversClass(ScimPlugin::class)]
final class ScimPluginTest extends TestCase
{
    #[Test]
    public function getCompilerPassesReturnsThreePasses(): void
    {
        $plugin = new ScimPlugin(__FILE__);

        $passes = $plugin->getCompilerPasses();

        self::assertCount(3, $passes);
        self::assertInstanceOf(RegisterAuthenticatorsPass::class, $passes[0]);
        self::assertInstanceOf(RegisterEventListenersPass::class, $passes[1]);
        self::assertInstanceOf(RegisterRestControllersPass::class, $passes[2]);
    }

    #[Test]
    public function onActivateDoesNotThrow(): void
    {
        $plugin = new ScimPlugin(__FILE__);

        // ScimPlugin inherits the no-op onActivate from AbstractPlugin
        $plugin->onActivate();

        self::assertTrue(true);
    }

    #[Test]
    public function onDeactivateDoesNotThrow(): void
    {
        $plugin = new ScimPlugin(__FILE__);

        // ScimPlugin inherits the no-op onDeactivate from AbstractPlugin
        $plugin->onDeactivate();

        self::assertTrue(true);
    }
}
