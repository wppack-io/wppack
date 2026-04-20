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

namespace WPPack\Component\Security\Bridge\OAuth\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Bridge\OAuth\Event\OAuthResponseReceivedEvent;
use WPPack\Component\Security\Bridge\OAuth\Event\OAuthUserProvisionedEvent;
use WPPack\Component\Security\Bridge\OAuth\Event\OAuthUserProvisionFailedEvent;
use WPPack\Component\Security\Bridge\OAuth\Event\OAuthUserUpdatedEvent;
use WPPack\Component\Security\Bridge\OAuth\Token\OAuthTokenSet;

#[CoversClass(OAuthResponseReceivedEvent::class)]
#[CoversClass(OAuthUserProvisionedEvent::class)]
#[CoversClass(OAuthUserProvisionFailedEvent::class)]
#[CoversClass(OAuthUserUpdatedEvent::class)]
final class OAuthEventsTest extends TestCase
{
    private function makeUser(string $prefix): \WP_User
    {
        $userId = (int) \wp_insert_user([
            'user_login' => $prefix . '_' . \uniqid(),
            'user_email' => $prefix . '_' . \uniqid() . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]);

        return new \WP_User($userId);
    }

    #[Test]
    public function responseReceivedEventCarriesSubjectClaimsAndTokenSet(): void
    {
        $tokens = new OAuthTokenSet('at', 'Bearer');
        $event = new OAuthResponseReceivedEvent('subject-123', ['email' => 'a@example.com'], $tokens);

        self::assertSame('subject-123', $event->getSubject());
        self::assertSame(['email' => 'a@example.com'], $event->getClaims());
        self::assertSame($tokens, $event->getTokenSet());
    }

    #[Test]
    public function userProvisionedEventCarriesUserSubjectClaims(): void
    {
        $user = $this->makeUser('oauth_provisioned');
        $event = new OAuthUserProvisionedEvent($user, 'sub-1', ['iss' => 'idp']);

        self::assertSame($user, $event->getUser());
        self::assertSame('sub-1', $event->getSubject());
        self::assertSame(['iss' => 'idp'], $event->getClaims());
    }

    #[Test]
    public function userUpdatedEventCarriesUserAndClaims(): void
    {
        $user = $this->makeUser('oauth_updated');
        $event = new OAuthUserUpdatedEvent($user, ['role' => 'editor']);

        self::assertSame($user, $event->getUser());
        self::assertSame(['role' => 'editor'], $event->getClaims());
    }

    #[Test]
    public function userProvisionFailedEventCarriesSubjectAndError(): void
    {
        $error = new \WP_Error('oauth_provision_failed', 'detail');
        $event = new OAuthUserProvisionFailedEvent('sub-2', $error);

        self::assertSame('sub-2', $event->getSubject());
        self::assertSame($error, $event->getError());
    }
}
