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

namespace WpPack\Component\DependencyInjection\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\Attribute\Autowire;
use WpPack\Component\DependencyInjection\Attribute\Constant;
use WpPack\Component\DependencyInjection\Attribute\Env;
use WpPack\Component\DependencyInjection\Attribute\Option;

final class AutowireTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $attr = new Autowire();

        self::assertNull($attr->env);
        self::assertNull($attr->param);
        self::assertNull($attr->service);
        self::assertNull($attr->option);
        self::assertNull($attr->constant);
    }

    #[Test]
    public function envValue(): void
    {
        $attr = new Autowire(env: 'DATABASE_URL');

        self::assertSame('DATABASE_URL', $attr->env);
        self::assertNull($attr->param);
        self::assertNull($attr->service);
        self::assertNull($attr->option);
        self::assertNull($attr->constant);
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
    public function optionValue(): void
    {
        $attr = new Autowire(option: 'my_settings.key');

        self::assertNull($attr->env);
        self::assertNull($attr->param);
        self::assertNull($attr->service);
        self::assertSame('my_settings.key', $attr->option);
        self::assertNull($attr->constant);
    }

    #[Test]
    public function constantValue(): void
    {
        $attr = new Autowire(constant: 'WP_DEBUG');

        self::assertNull($attr->env);
        self::assertNull($attr->param);
        self::assertNull($attr->service);
        self::assertNull($attr->option);
        self::assertSame('WP_DEBUG', $attr->constant);
    }

    #[Test]
    public function envShorthandAttribute(): void
    {
        $attr = new Env('APP_KEY');

        self::assertInstanceOf(Autowire::class, $attr);
        self::assertSame('APP_KEY', $attr->env);
        self::assertNull($attr->param);
    }

    #[Test]
    public function optionShorthandAttribute(): void
    {
        $attr = new Option('my_settings.key');

        self::assertInstanceOf(Autowire::class, $attr);
        self::assertSame('my_settings.key', $attr->option);
        self::assertNull($attr->env);
    }

    #[Test]
    public function constantShorthandAttribute(): void
    {
        $attr = new Constant('WP_DEBUG');

        self::assertInstanceOf(Autowire::class, $attr);
        self::assertSame('WP_DEBUG', $attr->constant);
        self::assertNull($attr->env);
    }

    #[Test]
    public function parsesFromReflection(): void
    {
        $reflection = new \ReflectionClass(Fixtures\AnnotatedService::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        $params = $constructor->getParameters();

        $envAttrs = $params[0]->getAttributes(Autowire::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $envAttrs);
        /** @var Autowire $envAutowire */
        $envAutowire = $envAttrs[0]->newInstance();
        self::assertSame('APP_ENV', $envAutowire->env);

        $paramAttrs = $params[1]->getAttributes(Autowire::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $paramAttrs);
        /** @var Autowire $paramAutowire */
        $paramAutowire = $paramAttrs[0]->newInstance();
        self::assertSame('app.debug', $paramAutowire->param);
    }
}
