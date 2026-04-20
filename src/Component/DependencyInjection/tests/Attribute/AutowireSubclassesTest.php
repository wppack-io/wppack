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

namespace WPPack\Component\DependencyInjection\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\Attribute\Autowire;
use WPPack\Component\DependencyInjection\Attribute\Constant;
use WPPack\Component\DependencyInjection\Attribute\Env;
use WPPack\Component\DependencyInjection\Attribute\Exclude;
use WPPack\Component\DependencyInjection\Attribute\Option;

#[CoversClass(Constant::class)]
#[CoversClass(Env::class)]
#[CoversClass(Option::class)]
#[CoversClass(Exclude::class)]
final class AutowireSubclassesTest extends TestCase
{
    #[Test]
    public function constantDelegatesToAutowireConstantSlot(): void
    {
        $attr = new Constant('WP_DEBUG');

        self::assertInstanceOf(Autowire::class, $attr);
        self::assertSame('WP_DEBUG', $attr->constant);
        self::assertNull($attr->env);
        self::assertNull($attr->param);
        self::assertNull($attr->service);
        self::assertNull($attr->option);
    }

    #[Test]
    public function envDelegatesToAutowireEnvSlot(): void
    {
        $attr = new Env('DATABASE_URL');

        self::assertInstanceOf(Autowire::class, $attr);
        self::assertSame('DATABASE_URL', $attr->env);
        self::assertNull($attr->constant);
        self::assertNull($attr->param);
        self::assertNull($attr->service);
        self::assertNull($attr->option);
    }

    #[Test]
    public function optionDelegatesToAutowireOptionSlot(): void
    {
        $attr = new Option('siteurl');

        self::assertInstanceOf(Autowire::class, $attr);
        self::assertSame('siteurl', $attr->option);
        self::assertNull($attr->env);
        self::assertNull($attr->param);
        self::assertNull($attr->service);
        self::assertNull($attr->constant);
    }

    #[Test]
    public function excludeIsClassLevelMarker(): void
    {
        $attr = new Exclude();

        self::assertSame([], get_object_vars($attr));

        $ref = new \ReflectionClass(Exclude::class);
        $attribute = $ref->getAttributes(\Attribute::class)[0];
        self::assertSame(\Attribute::TARGET_CLASS, $attribute->getArguments()[0]);
    }

    #[Test]
    public function constantEnvOptionTargetParameters(): void
    {
        foreach ([Constant::class, Env::class, Option::class] as $class) {
            $ref = new \ReflectionClass($class);
            $attribute = $ref->getAttributes(\Attribute::class)[0];
            self::assertSame(\Attribute::TARGET_PARAMETER, $attribute->getArguments()[0], $class);
        }
    }
}
