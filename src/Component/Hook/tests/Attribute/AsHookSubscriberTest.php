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

namespace WpPack\Component\Hook\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\AsHookSubscriber;

final class AsHookSubscriberTest extends TestCase
{
    #[Test]
    public function isUsableAsClassAttribute(): void
    {
        $reflection = new \ReflectionClass(AsHookSubscriber::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        /** @var \Attribute $attribute */
        $attribute = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attribute->flags);
    }

    #[Test]
    public function isDetectableViaReflection(): void
    {
        $class = new #[AsHookSubscriber] class {};

        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(AsHookSubscriber::class);

        self::assertCount(1, $attributes);
    }
}
