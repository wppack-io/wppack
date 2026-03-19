<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Routing\Attribute\RewriteTag;

#[CoversClass(RewriteTag::class)]
final class RewriteTagTest extends TestCase
{
    #[Test]
    public function storesTagAndRegex(): void
    {
        $tag = new RewriteTag(
            tag: '%product_slug%',
            regex: '([^/]+)',
        );

        self::assertSame('%product_slug%', $tag->tag);
        self::assertSame('([^/]+)', $tag->regex);
    }

    #[Test]
    public function storesNumericRegex(): void
    {
        $tag = new RewriteTag(
            tag: '%item_id%',
            regex: '(\d+)',
        );

        self::assertSame('%item_id%', $tag->tag);
        self::assertSame('(\d+)', $tag->regex);
    }

    #[Test]
    public function isRepeatableAttribute(): void
    {
        $reflection = new \ReflectionClass(RewriteTag::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $expected = \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE;

        self::assertSame($expected, $attribute->flags);
    }

    #[Test]
    public function targetsClassAndMethod(): void
    {
        $reflection = new \ReflectionClass(RewriteTag::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $attribute = $attributes[0]->newInstance();

        self::assertTrue(($attribute->flags & \Attribute::TARGET_CLASS) !== 0);
        self::assertTrue(($attribute->flags & \Attribute::TARGET_METHOD) !== 0);
    }

    #[Test]
    public function canBeAppliedMultipleTimes(): void
    {
        // Verify by creating multiple instances (simulating multiple attribute usage)
        $tag1 = new RewriteTag('%slug%', '([^/]+)');
        $tag2 = new RewriteTag('%id%', '(\d+)');

        self::assertSame('%slug%', $tag1->tag);
        self::assertSame('%id%', $tag2->tag);
        self::assertNotSame($tag1->tag, $tag2->tag);
    }
}
