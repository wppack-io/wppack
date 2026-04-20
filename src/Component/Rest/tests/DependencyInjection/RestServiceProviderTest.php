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

namespace WPPack\Component\Rest\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Rest\DependencyInjection\RestServiceProvider;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Component\Rest\RestUrlGenerator;
use WPPack\Component\Role\Authorization\IsGrantedChecker;
use WPPack\Component\Security\Security;

#[CoversClass(RestServiceProvider::class)]
final class RestServiceProviderTest extends TestCase
{
    #[Test]
    public function registersRestRegistryAndUrlGenerator(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(Request::class);

        (new RestServiceProvider())->register($builder);

        self::assertTrue($builder->hasDefinition(RestRegistry::class));
        self::assertTrue($builder->hasDefinition(RestUrlGenerator::class));
    }

    #[Test]
    public function skipsSecurityWiringWhenSecurityIsAbsent(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(Request::class);

        (new RestServiceProvider())->register($builder);

        self::assertFalse($builder->hasDefinition(IsGrantedChecker::class));
    }

    #[Test]
    public function wiresSecurityAndIsGrantedCheckerWhenSecurityIsPresent(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(Request::class);
        $builder->register(Security::class);

        (new RestServiceProvider())->register($builder);

        self::assertTrue($builder->hasDefinition(RestRegistry::class));
        self::assertTrue($builder->hasDefinition(IsGrantedChecker::class));
    }

    #[Test]
    public function reusesExistingIsGrantedCheckerDefinition(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(Request::class);
        $builder->register(Security::class);
        $existing = $builder->register(IsGrantedChecker::class);

        (new RestServiceProvider())->register($builder);

        self::assertTrue($builder->hasDefinition(IsGrantedChecker::class));
        // The pre-existing definition is not overwritten — provider only registers if missing.
        self::assertSame($existing, $builder->findDefinition(IsGrantedChecker::class));
    }
}
