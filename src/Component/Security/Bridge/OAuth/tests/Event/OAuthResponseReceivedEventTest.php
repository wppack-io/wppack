<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\Event\OAuthResponseReceivedEvent;
use WpPack\Component\Security\Bridge\OAuth\Token\OAuthTokenSet;

#[CoversClass(OAuthResponseReceivedEvent::class)]
final class OAuthResponseReceivedEventTest extends TestCase
{
    #[Test]
    public function getSubject(): void
    {
        $tokenSet = new OAuthTokenSet('access-token', 'Bearer');
        $event = new OAuthResponseReceivedEvent('user@example.com', [], $tokenSet);

        self::assertSame('user@example.com', $event->getSubject());
    }

    #[Test]
    public function getClaims(): void
    {
        $claims = [
            'sub' => '12345',
            'email' => 'user@example.com',
        ];

        $tokenSet = new OAuthTokenSet('access-token', 'Bearer');
        $event = new OAuthResponseReceivedEvent('12345', $claims, $tokenSet);

        self::assertSame($claims, $event->getClaims());
    }

    #[Test]
    public function getTokenSet(): void
    {
        $tokenSet = new OAuthTokenSet('access-token', 'Bearer', idToken: 'id-token');
        $event = new OAuthResponseReceivedEvent('12345', [], $tokenSet);

        self::assertSame($tokenSet, $event->getTokenSet());
    }

    #[Test]
    public function propagationCanBeStopped(): void
    {
        $tokenSet = new OAuthTokenSet('access-token', 'Bearer');
        $event = new OAuthResponseReceivedEvent('12345', [], $tokenSet);

        self::assertFalse($event->isPropagationStopped());

        $event->stopPropagation();

        self::assertTrue($event->isPropagationStopped());
    }
}
