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

namespace WPPack\Component\Kernel\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Kernel\Attribute\TextDomain;

#[CoversClass(TextDomain::class)]
final class TextDomainTest extends TestCase
{
    #[Test]
    public function defaultsLanguageSubdirectoryToLanguages(): void
    {
        $attr = new TextDomain(domain: 'my-plugin');

        self::assertSame('my-plugin', $attr->domain);
        self::assertSame('languages', $attr->path);
    }

    #[Test]
    public function customPathIsPreserved(): void
    {
        $attr = new TextDomain(domain: 'my-plugin', path: 'build/locales');

        self::assertSame('build/locales', $attr->path);
    }

    #[Test]
    public function targetsClassesAndIsRepeatable(): void
    {
        $ref = new \ReflectionClass(TextDomain::class);
        $attribute = $ref->getAttributes(\Attribute::class)[0] ?? null;

        self::assertNotNull($attribute);
        $args = $attribute->getArguments();
        $flags = $args[0];

        self::assertSame(
            \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE,
            $flags,
        );
    }

    #[Test]
    public function attributeCanBeDiscoveredViaReflection(): void
    {
        $ref = new \ReflectionClass(SampleTextDomainTarget::class);
        $attributes = $ref->getAttributes(TextDomain::class);

        self::assertCount(2, $attributes);

        $instances = array_map(static fn(\ReflectionAttribute $a): TextDomain => $a->newInstance(), $attributes);
        self::assertSame('plugin-a', $instances[0]->domain);
        self::assertSame('languages', $instances[0]->path);
        self::assertSame('plugin-b', $instances[1]->domain);
        self::assertSame('other', $instances[1]->path);
    }
}

#[TextDomain(domain: 'plugin-a')]
#[TextDomain(domain: 'plugin-b', path: 'other')]
final class SampleTextDomainTarget {}
