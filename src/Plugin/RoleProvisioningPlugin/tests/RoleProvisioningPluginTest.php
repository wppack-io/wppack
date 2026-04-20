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
use Psr\Log\NullLogger;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Kernel\Attribute\TextDomain;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Site\BlogContext;
use WPPack\Component\User\UserRepository;
use WPPack\Plugin\RoleProvisioningPlugin\Admin\RoleProvisioningSettingsController;
use WPPack\Plugin\RoleProvisioningPlugin\Admin\RoleProvisioningSettingsPage;
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

    #[Test]
    public function bootRegistersAdminSettingsAndProvisioner(): void
    {
        $roleProvider = new RoleProvider();
        $blogContext = new BlogContext();

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(AdminPageRegistry::class, new AdminPageRegistry());
        $symfonyContainer->set(RoleProvisioningSettingsPage::class, new RoleProvisioningSettingsPage());
        $symfonyContainer->set(RestRegistry::class, new RestRegistry(new Request()));
        $symfonyContainer->set(
            RoleProvisioningSettingsController::class,
            new RoleProvisioningSettingsController($roleProvider),
        );
        $symfonyContainer->set(
            RoleProvisioner::class,
            new RoleProvisioner(
                new RoleProvisioningConfiguration(),
                $roleProvider,
                $blogContext,
                new UserRepository(),
                new NullLogger(),
            ),
        );

        $container = new Container($symfonyContainer);
        $plugin = new RoleProvisioningPlugin(__FILE__);

        $plugin->boot($container);

        // Admin settings page + REST endpoint + provisioner hooks attached
        self::assertNotFalse(has_action('admin_menu') ?: has_action('network_admin_menu'));
        self::assertNotFalse(has_action('rest_api_init'));
        // RoleProvisioner::register attaches a user-login / profile filter
        self::assertNotFalse(
            has_action('wp_login')
            ?: has_action('user_register')
            ?: has_filter('wppack/scim/user_attributes'),
        );

        remove_all_actions('admin_menu');
        remove_all_actions('network_admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
        remove_all_actions('wp_login');
        remove_all_actions('user_register');
        remove_all_filters('wppack/scim/user_attributes');
    }
}
