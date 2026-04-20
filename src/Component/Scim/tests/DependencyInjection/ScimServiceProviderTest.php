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

namespace WPPack\Component\Scim\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Sanitizer\Sanitizer;
use WPPack\Component\Scim\Authentication\ScimUserStatusChecker;
use WPPack\Component\Scim\Controller\GroupController;
use WPPack\Component\Scim\Controller\ResourceTypeController;
use WPPack\Component\Scim\Controller\SchemaController;
use WPPack\Component\Scim\Controller\ServiceProviderConfigController;
use WPPack\Component\Scim\Controller\UserController;
use WPPack\Component\Scim\DependencyInjection\ScimServiceProvider;
use WPPack\Component\Scim\Filter\FilterParser;
use WPPack\Component\Scim\Filter\WpUserQueryAdapter;
use WPPack\Component\Scim\Mapping\GroupMapper;
use WPPack\Component\Scim\Mapping\GroupMapperInterface;
use WPPack\Component\Scim\Mapping\UserAttributeMapper;
use WPPack\Component\Scim\Mapping\UserAttributeMapperInterface;
use WPPack\Component\Scim\Patch\PatchProcessor;
use WPPack\Component\Scim\Repository\ScimGroupRepository;
use WPPack\Component\Scim\Repository\ScimUserRepository;
use WPPack\Component\Scim\Schema\ServiceProviderConfig;
use WPPack\Component\Scim\Serialization\ScimGroupSerializer;
use WPPack\Component\Scim\Serialization\ScimUserSerializer;

#[CoversClass(ScimServiceProvider::class)]
final class ScimServiceProviderTest extends TestCase
{
    #[Test]
    public function registersEveryScimService(): void
    {
        $builder = new ContainerBuilder();

        (new ScimServiceProvider())->register($builder);

        foreach ([
            Sanitizer::class,
            RoleProvider::class,
            ServiceProviderConfig::class,
            UserAttributeMapper::class,
            GroupMapper::class,
            ScimUserStatusChecker::class,
            ScimUserSerializer::class,
            ScimGroupSerializer::class,
            FilterParser::class,
            WpUserQueryAdapter::class,
            PatchProcessor::class,
            ScimUserRepository::class,
            ScimGroupRepository::class,
            UserController::class,
            GroupController::class,
            ServiceProviderConfigController::class,
            SchemaController::class,
            ResourceTypeController::class,
        ] as $id) {
            self::assertTrue($builder->hasDefinition($id), "definition missing: {$id}");
        }
    }

    #[Test]
    public function mapperInterfaceAliasesAreRegistered(): void
    {
        $builder = new ContainerBuilder();

        (new ScimServiceProvider())->register($builder);

        $symfony = $builder->getSymfonyBuilder();
        self::assertTrue($symfony->hasAlias(UserAttributeMapperInterface::class));
        self::assertSame(UserAttributeMapper::class, (string) $symfony->getAlias(UserAttributeMapperInterface::class));

        self::assertTrue($symfony->hasAlias(GroupMapperInterface::class));
        self::assertSame(GroupMapper::class, (string) $symfony->getAlias(GroupMapperInterface::class));
    }

    #[Test]
    public function allRestControllersAreTagged(): void
    {
        $builder = new ContainerBuilder();

        (new ScimServiceProvider())->register($builder);

        $taggedIds = array_keys($builder->findTaggedServiceIds('rest.controller'));

        foreach ([
            UserController::class,
            GroupController::class,
            ServiceProviderConfigController::class,
            SchemaController::class,
            ResourceTypeController::class,
        ] as $controller) {
            self::assertContains($controller, $taggedIds, "{$controller} not tagged as rest.controller");
        }
    }

    #[Test]
    public function preExistingSharedServicesAreReused(): void
    {
        $builder = new ContainerBuilder();
        $sanitizer = $builder->register(Sanitizer::class);
        $roleProvider = $builder->register(RoleProvider::class);

        (new ScimServiceProvider())->register($builder);

        self::assertSame($sanitizer, $builder->findDefinition(Sanitizer::class));
        self::assertSame($roleProvider, $builder->findDefinition(RoleProvider::class));
    }
}
