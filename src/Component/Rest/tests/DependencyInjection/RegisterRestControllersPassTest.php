<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Rest\Attribute\Permission;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Rest\RestRegistry;

final class RegisterRestControllersPassTest extends TestCase
{
    #[Test]
    public function skipsWhenRegistryNotDefined(): void
    {
        $builder = new ContainerBuilder();
        $pass = new RegisterRestControllersPass();

        $pass->process($builder);

        self::assertFalse($builder->hasDefinition(RestRegistry::class));
    }

    #[Test]
    public function detectsControllersByAttribute(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(RestRegistry::class);
        $builder->register(PassTestController::class);

        $pass = new RegisterRestControllersPass();
        $pass->process($builder);

        $registryDef = $builder->findDefinition(RestRegistry::class);
        $methodCalls = $registryDef->getMethodCalls();

        $registerCalls = array_filter($methodCalls, static fn(array $call): bool => $call['method'] === 'register');
        self::assertCount(1, $registerCalls);
    }

    #[Test]
    public function detectsControllersByTag(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(RestRegistry::class);
        $builder->register(PassTestTaggedController::class)->addTag(RegisterRestControllersPass::TAG);

        $pass = new RegisterRestControllersPass();
        $pass->process($builder);

        $registryDef = $builder->findDefinition(RestRegistry::class);
        $methodCalls = $registryDef->getMethodCalls();

        $registerCalls = array_filter($methodCalls, static fn(array $call): bool => $call['method'] === 'register');
        self::assertCount(1, $registerCalls);
    }

    #[Test]
    public function ignoresNonControllerClasses(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(RestRegistry::class);
        $builder->register(\stdClass::class);

        $pass = new RegisterRestControllersPass();
        $pass->process($builder);

        $registryDef = $builder->findDefinition(RestRegistry::class);
        $methodCalls = $registryDef->getMethodCalls();

        $registerCalls = array_filter($methodCalls, static fn(array $call): bool => $call['method'] === 'register');
        self::assertCount(0, $registerCalls);
    }

    #[Test]
    public function skipsNonExistentClass(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(RestRegistry::class);
        $builder->register('non_existent_service', 'NonExistent\\RestController');

        $pass = new RegisterRestControllersPass();
        $pass->process($builder);

        $registryDef = $builder->findDefinition(RestRegistry::class);
        $methodCalls = $registryDef->getMethodCalls();

        $registerCalls = array_filter($methodCalls, static fn(array $call): bool => $call['method'] === 'register');
        self::assertCount(0, $registerCalls);
    }
}

#[RestRoute('/pass-test', namespace: 'test/v1')]
#[Permission(public: true)]
final class PassTestController
{
    #[RestRoute(methods: HttpMethod::GET)]
    public function index(): array
    {
        return [];
    }
}

#[RestRoute('/pass-test-tagged', namespace: 'test/v1')]
#[Permission(public: true)]
final class PassTestTaggedController
{
    #[RestRoute(methods: HttpMethod::GET)]
    public function index(): array
    {
        return [];
    }
}
