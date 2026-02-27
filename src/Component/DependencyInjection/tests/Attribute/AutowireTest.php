<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\Attribute\Autowire;

final class AutowireTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $attr = new Autowire();

        self::assertNull($attr->env);
        self::assertNull($attr->param);
        self::assertNull($attr->service);
    }

    #[Test]
    public function envValue(): void
    {
        $attr = new Autowire(env: 'DATABASE_URL');

        self::assertSame('DATABASE_URL', $attr->env);
        self::assertNull($attr->param);
        self::assertNull($attr->service);
    }

    #[Test]
    public function paramValue(): void
    {
        $attr = new Autowire(param: 'app.name');

        self::assertNull($attr->env);
        self::assertSame('app.name', $attr->param);
        self::assertNull($attr->service);
    }

    #[Test]
    public function serviceValue(): void
    {
        $attr = new Autowire(service: 'my.service');

        self::assertNull($attr->env);
        self::assertNull($attr->param);
        self::assertSame('my.service', $attr->service);
    }

    #[Test]
    public function parsesFromReflection(): void
    {
        $reflection = new \ReflectionClass(Fixtures\AnnotatedService::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        $params = $constructor->getParameters();

        $envAttrs = $params[0]->getAttributes(Autowire::class);
        self::assertCount(1, $envAttrs);
        /** @var Autowire $envAutowire */
        $envAutowire = $envAttrs[0]->newInstance();
        self::assertSame('APP_ENV', $envAutowire->env);

        $paramAttrs = $params[1]->getAttributes(Autowire::class);
        self::assertCount(1, $paramAttrs);
        /** @var Autowire $paramAutowire */
        $paramAutowire = $paramAttrs[0]->newInstance();
        self::assertSame('app.debug', $paramAutowire->param);
    }
}
