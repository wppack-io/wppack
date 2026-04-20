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

namespace WPPack\Plugin\RoleProvisioningPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Kernel\Attribute\TextDomain;
use WPPack\Plugin\RoleProvisioningPlugin\Configuration\RoleProvisioningConfiguration;
use WPPack\Plugin\RoleProvisioningPlugin\Provisioning\RoleProvisioner;
use WPPack\Plugin\RoleProvisioningPlugin\RoleProvisioningPlugin;

#[CoversClass(RoleProvisioningPlugin::class)]
final class RoleProvisioningPluginTest extends TestCase
{
    #[Test]
    public function pluginDeclaresTextDomainAttribute(): void
    {
        $ref = new \ReflectionClass(RoleProvisioningPlugin::class);
        $attr = $ref->getAttributes(TextDomain::class)[0] ?? null;

        self::assertNotNull($attr);
        self::assertSame('wppack-role-provisioning', $attr->newInstance()->domain);
    }

    #[Test]
    public function registerDelegatesToServiceProvider(): void
    {
        $plugin = new RoleProvisioningPlugin(__FILE__);
        $builder = new ContainerBuilder();

        $plugin->register($builder);

        self::assertTrue($builder->hasDefinition(RoleProvisioner::class));
        self::assertTrue($builder->hasDefinition(RoleProvisioningConfiguration::class));
    }

    #[Test]
    public function getFileReturnsConstructorArgument(): void
    {
        $path = '/fake/wppack-role-provisioning.php';
        $plugin = new RoleProvisioningPlugin($path);

        self::assertSame($path, $plugin->getFile());
    }
}
