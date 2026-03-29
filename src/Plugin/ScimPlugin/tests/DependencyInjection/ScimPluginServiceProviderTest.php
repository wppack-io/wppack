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

namespace WpPack\Plugin\ScimPlugin\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\EventDispatcher\EventDispatcher;
use WpPack\Component\Scim\Authentication\ScimBearerAuthenticator;
use WpPack\Component\Scim\Controller\GroupController;
use WpPack\Component\Scim\Controller\ResourceTypeController;
use WpPack\Component\Scim\Controller\SchemaController;
use WpPack\Component\Scim\Controller\ServiceProviderConfigController;
use WpPack\Component\Scim\Controller\UserController;
use WpPack\Component\Scim\Schema\ServiceProviderConfig;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Plugin\ScimPlugin\Configuration\ScimConfiguration;
use WpPack\Plugin\ScimPlugin\DependencyInjection\ScimPluginServiceProvider;

#[CoversClass(ScimPluginServiceProvider::class)]
final class ScimPluginServiceProviderTest extends TestCase
{
    private ContainerBuilder $builder;
    private ScimPluginServiceProvider $provider;

    protected function setUp(): void
    {
        $this->builder = new ContainerBuilder();
        $this->provider = new ScimPluginServiceProvider();
    }

    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        self::assertInstanceOf(ServiceProviderInterface::class, $this->provider);
    }

    #[Test]
    public function registersConfiguration(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(ScimConfiguration::class));

        $definition = $this->builder->findDefinition(ScimConfiguration::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(ScimConfiguration::class, $factory[0]);
        self::assertSame('fromEnvironment', $factory[1]);
    }

    #[Test]
    public function registersServiceProviderConfig(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(ServiceProviderConfig::class));

        $definition = $this->builder->findDefinition(ServiceProviderConfig::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(ScimPluginServiceProvider::class, $factory[0]);
        self::assertSame('createServiceProviderConfig', $factory[1]);

        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(ScimConfiguration::class, (string) $arguments[0]);
    }

    #[Test]
    public function registersScimBearerAuthenticatorWithTag(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(ScimBearerAuthenticator::class));

        $definition = $this->builder->findDefinition(ScimBearerAuthenticator::class);
        self::assertTrue($definition->hasTag('security.authenticator'));

        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(ScimPluginServiceProvider::class, $factory[0]);
        self::assertSame('createAuthenticator', $factory[1]);

        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(ScimConfiguration::class, (string) $arguments[0]);
    }

    #[Test]
    public function registersControllers(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(UserController::class));
        self::assertTrue($this->builder->hasDefinition(GroupController::class));
        self::assertTrue($this->builder->hasDefinition(ServiceProviderConfigController::class));
        self::assertTrue($this->builder->hasDefinition(SchemaController::class));
        self::assertTrue($this->builder->hasDefinition(ResourceTypeController::class));
    }

    #[Test]
    public function controllersHaveRestControllerTag(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->findDefinition(UserController::class)->hasTag('rest.controller'));
        self::assertTrue($this->builder->findDefinition(GroupController::class)->hasTag('rest.controller'));
        self::assertTrue($this->builder->findDefinition(ServiceProviderConfigController::class)->hasTag('rest.controller'));
        self::assertTrue($this->builder->findDefinition(SchemaController::class)->hasTag('rest.controller'));
        self::assertTrue($this->builder->findDefinition(ResourceTypeController::class)->hasTag('rest.controller'));
    }

    #[Test]
    public function autoRegistersEventDispatcherServiceProvider(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(EventDispatcher::class));
    }

    #[Test]
    public function autoRegistersSecurityServiceProvider(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(AuthenticationManager::class));
    }

    #[Test]
    public function doesNotOverrideExistingEventDispatcher(): void
    {
        $this->builder->register(EventDispatcher::class)
            ->addArgument('custom');

        $this->provider->register($this->builder);

        $definition = $this->builder->findDefinition(EventDispatcher::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertSame('custom', $arguments[0]);
    }

    #[Test]
    public function doesNotOverrideExistingAuthenticationManager(): void
    {
        $this->builder->register(AuthenticationManager::class)
            ->addArgument('custom');

        $this->provider->register($this->builder);

        $definition = $this->builder->findDefinition(AuthenticationManager::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertSame('custom', $arguments[0]);
    }

    #[Test]
    public function createServiceProviderConfigReturnsServiceProviderConfig(): void
    {
        $config = new ScimConfiguration(
            bearerToken: 'test-token',
            maxResults: 50,
        );

        $result = ScimPluginServiceProvider::createServiceProviderConfig($config);

        self::assertInstanceOf(ServiceProviderConfig::class, $result);
        self::assertSame(50, $result->maxResults);
    }

    #[Test]
    public function createAuthenticatorReturnsScimBearerAuthenticator(): void
    {
        $config = new ScimConfiguration(
            bearerToken: 'test-token',
        );

        $result = ScimPluginServiceProvider::createAuthenticator($config);

        self::assertInstanceOf(ScimBearerAuthenticator::class, $result);
    }

    #[Test]
    public function setsContainerParametersViaControllerArguments(): void
    {
        $this->provider->register($this->builder);

        $userArgs = $this->builder->findDefinition(UserController::class)->getArguments();
        self::assertSame('%scim.max_results%', $userArgs['$maxResults']);
        self::assertSame('%scim.base_url%', $userArgs['$baseUrl']);
        self::assertSame('%scim.default_role%', $userArgs['$defaultRole']);
        self::assertSame('%scim.allow_user_deletion%', $userArgs['$allowUserDeletion']);
        self::assertSame('%scim.auto_provision%', $userArgs['$autoProvision']);

        $groupArgs = $this->builder->findDefinition(GroupController::class)->getArguments();
        self::assertSame('%scim.max_results%', $groupArgs['$maxResults']);
        self::assertSame('%scim.base_url%', $groupArgs['$baseUrl']);
        self::assertSame('%scim.allow_group_management%', $groupArgs['$allowGroupManagement']);

        $spcArgs = $this->builder->findDefinition(ServiceProviderConfigController::class)->getArguments();
        self::assertSame('%scim.base_url%', $spcArgs['$baseUrl']);

        $schemaArgs = $this->builder->findDefinition(SchemaController::class)->getArguments();
        self::assertSame('%scim.base_url%', $schemaArgs['$baseUrl']);

        $rtArgs = $this->builder->findDefinition(ResourceTypeController::class)->getArguments();
        self::assertSame('%scim.base_url%', $rtArgs['$baseUrl']);
    }
}
