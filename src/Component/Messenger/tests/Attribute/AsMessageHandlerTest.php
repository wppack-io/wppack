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

namespace WpPack\Component\Messenger\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Attribute\AsMessageHandler;

#[CoversClass(AsMessageHandler::class)]
final class AsMessageHandlerTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $attribute = new AsMessageHandler();

        self::assertNull($attribute->bus);
        self::assertNull($attribute->fromTransport);
        self::assertNull($attribute->handles);
        self::assertNull($attribute->method);
        self::assertSame(0, $attribute->priority);
    }

    #[Test]
    public function customValues(): void
    {
        $attribute = new AsMessageHandler(
            bus: 'command.bus',
            fromTransport: 'sqs',
            handles: 'App\\Message\\SendEmail',
            method: 'handleSendEmail',
            priority: 10,
        );

        self::assertSame('command.bus', $attribute->bus);
        self::assertSame('sqs', $attribute->fromTransport);
        self::assertSame('App\\Message\\SendEmail', $attribute->handles);
        self::assertSame('handleSendEmail', $attribute->method);
        self::assertSame(10, $attribute->priority);
    }

    #[Test]
    public function isAttributeClass(): void
    {
        $ref = new \ReflectionClass(AsMessageHandler::class);
        $attributes = $ref->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attr = $attributes[0]->newInstance();
        self::assertSame(
            \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE,
            $attr->flags,
        );
    }
}
