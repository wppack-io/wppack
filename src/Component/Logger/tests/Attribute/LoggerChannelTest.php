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

namespace WpPack\Component\Logger\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Logger\Attribute\LoggerChannel;

final class LoggerChannelTest extends TestCase
{
    #[Test]
    public function channelProperty(): void
    {
        $attribute = new LoggerChannel(channel: 'payment');

        self::assertSame('payment', $attribute->channel);
    }

    #[Test]
    public function targetParameter(): void
    {
        $reflection = new \ReflectionClass(LoggerChannel::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_PARAMETER, $attribute->flags);
    }
}
