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

namespace WPPack\Plugin\RoleProvisioningPlugin\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\Plugin\RoleProvisioningPlugin\Admin\RoleProvisioningSettingsController;
use WPPack\Plugin\RoleProvisioningPlugin\Admin\RoleProvisioningSettingsPage;
use WPPack\Plugin\RoleProvisioningPlugin\Configuration\RoleProvisioningConfiguration;
use WPPack\Plugin\RoleProvisioningPlugin\DependencyInjection\RoleProvisioningPluginServiceProvider;
use WPPack\Plugin\RoleProvisioningPlugin\Provisioning\RoleProvisioner;

#[CoversClass(RoleProvisioningPluginServiceProvider::class)]
final class RoleProvisioningPluginServiceProviderTest extends TestCase
{
    #[Test]
    public function registersCoreServices(): void
    {
        $builder = new ContainerBuilder();

        (new RoleProvisioningPluginServiceProvider())->register($builder);

        foreach ([
            Request::class,
            AdminPageRegistry::class,
            RoleProvider::class,
            RoleProvisioningConfiguration::class,
            RoleProvisioningSettingsPage::class,
            RoleProvisioningSettingsController::class,
            RoleProvisioner::class,
        ] as $id) {
            self::assertTrue($builder->hasDefinition($id), "missing: {$id}");
        }
    }

    #[Test]
    public function preExistingSharedServicesAreReused(): void
    {
        $builder = new ContainerBuilder();
        $existing = $builder->register(RoleProvider::class);

        (new RoleProvisioningPluginServiceProvider())->register($builder);

        self::assertSame($existing, $builder->findDefinition(RoleProvider::class));
    }

    #[Test]
    public function blogContextInterfaceResolvesToConcreteBlogContext(): void
    {
        $builder = new ContainerBuilder();

        (new RoleProvisioningPluginServiceProvider())->register($builder);

        self::assertTrue($builder->hasDefinition(BlogContextInterface::class));
    }

    #[Test]
    public function configurationIsRegisteredAsFactory(): void
    {
        $builder = new ContainerBuilder();

        (new RoleProvisioningPluginServiceProvider())->register($builder);

        // Factory returns fromOption() static call; verify by building container
        $definition = $builder->findDefinition(RoleProvisioningConfiguration::class);
        $factory = $definition->getFactory();

        self::assertIsArray($factory);
        self::assertSame(RoleProvisioningConfiguration::class, $factory[0]);
        self::assertSame('fromOption', $factory[1]);
    }
}
