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

namespace WPPack\Component\Feed\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Feed\Attribute\AsFeed;

#[CoversClass(AsFeed::class)]
final class AsFeedTest extends TestCase
{
    #[Test]
    public function storesSlugAndLabel(): void
    {
        $attr = new AsFeed(slug: 'custom', label: 'Custom Feed');

        self::assertSame('custom', $attr->slug);
        self::assertSame('Custom Feed', $attr->label);
    }

    #[Test]
    public function labelDefaultsToEmptyString(): void
    {
        $attr = new AsFeed(slug: 'rss2');

        self::assertSame('', $attr->label);
    }

    #[Test]
    public function targetsClassesOnly(): void
    {
        $ref = new \ReflectionClass(AsFeed::class);
        $attribute = $ref->getAttributes(\Attribute::class)[0] ?? null;

        self::assertNotNull($attribute);
        self::assertSame(\Attribute::TARGET_CLASS, $attribute->getArguments()[0]);
    }
}
