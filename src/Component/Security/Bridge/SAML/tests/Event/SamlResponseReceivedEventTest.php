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

namespace WpPack\Component\Security\Bridge\SAML\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\SAML\Event\SamlResponseReceivedEvent;

#[CoversClass(SamlResponseReceivedEvent::class)]
final class SamlResponseReceivedEventTest extends TestCase
{
    #[Test]
    public function getNameIdReturnsNameId(): void
    {
        $event = new SamlResponseReceivedEvent(
            'user@example.com',
            ['email' => ['user@example.com']],
            '_session123',
        );

        self::assertSame('user@example.com', $event->getNameId());
    }

    #[Test]
    public function getAttributesReturnsAttributes(): void
    {
        $attributes = [
            'email' => ['user@example.com'],
            'role' => ['admin', 'editor'],
            'department' => ['Engineering'],
        ];

        $event = new SamlResponseReceivedEvent('user@example.com', $attributes, null);

        self::assertSame($attributes, $event->getAttributes());
    }

    #[Test]
    public function getSessionIndexReturnsSessionIndex(): void
    {
        $event = new SamlResponseReceivedEvent(
            'user@example.com',
            [],
            '_session456',
        );

        self::assertSame('_session456', $event->getSessionIndex());
    }

    #[Test]
    public function getSessionIndexReturnsNullWhenNull(): void
    {
        $event = new SamlResponseReceivedEvent(
            'user@example.com',
            [],
            null,
        );

        self::assertNull($event->getSessionIndex());
    }

    #[Test]
    public function getAttributesReturnsEmptyArrayWhenNoAttributes(): void
    {
        $event = new SamlResponseReceivedEvent('user@example.com', [], '_session');

        self::assertSame([], $event->getAttributes());
    }

    #[Test]
    public function eventPreservesMultiValuedAttributes(): void
    {
        $attributes = [
            'groups' => ['Admin', 'Users', 'Developers'],
        ];

        $event = new SamlResponseReceivedEvent('user@example.com', $attributes, null);

        self::assertSame(['Admin', 'Users', 'Developers'], $event->getAttributes()['groups']);
    }
}
