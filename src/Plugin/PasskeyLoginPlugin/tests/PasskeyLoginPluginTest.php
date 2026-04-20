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

namespace WPPack\Plugin\PasskeyLoginPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Kernel\Attribute\TextDomain;
use WPPack\Plugin\PasskeyLoginPlugin\Configuration\PasskeyLoginConfiguration;
use WPPack\Plugin\PasskeyLoginPlugin\PasskeyLoginPlugin;

#[CoversClass(PasskeyLoginPlugin::class)]
final class PasskeyLoginPluginTest extends TestCase
{
    #[Test]
    public function pluginDeclaresTextDomainAttribute(): void
    {
        $ref = new \ReflectionClass(PasskeyLoginPlugin::class);
        $attributes = $ref->getAttributes(TextDomain::class);

        self::assertNotEmpty($attributes);
        $textDomain = $attributes[0]->newInstance();
        self::assertSame('wppack-passkey-login', $textDomain->domain);
    }

    #[Test]
    public function onActivateDoesNothingWhenSchemaManagerIsNull(): void
    {
        $plugin = new PasskeyLoginPlugin(__FILE__);

        // boot() hasn't been called — SchemaManager is null, onActivate is a no-op
        $plugin->onActivate();

        self::assertTrue(true, 'no exception thrown');
    }

    #[Test]
    public function registerPopulatesBuilderWithPluginServices(): void
    {
        $plugin = new PasskeyLoginPlugin(__FILE__);
        $builder = new ContainerBuilder();

        // Pre-register Request so nested providers that depend on it work
        $builder->register(Request::class);

        $plugin->register($builder);

        // Plugin-level services
        self::assertTrue($builder->hasDefinition(PasskeyLoginConfiguration::class));
    }

    #[Test]
    public function getFileReturnsPluginFilePath(): void
    {
        $pluginFile = '/fake/wppack-passkey-login.php';
        $plugin = new PasskeyLoginPlugin($pluginFile);

        self::assertSame($pluginFile, $plugin->getFile());
    }
}
