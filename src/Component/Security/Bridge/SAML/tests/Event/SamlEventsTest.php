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

namespace WPPack\Component\Security\Bridge\SAML\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Bridge\SAML\Event\SamlResponseReceivedEvent;
use WPPack\Component\Security\Bridge\SAML\Event\SamlUserAttributesMappedEvent;
use WPPack\Component\Security\Bridge\SAML\Event\SamlUserProvisionedEvent;
use WPPack\Component\Security\Bridge\SAML\Event\SamlUserProvisionFailedEvent;
use WPPack\Component\Security\Bridge\SAML\Event\SamlUserUpdatedEvent;

#[CoversClass(SamlResponseReceivedEvent::class)]
#[CoversClass(SamlUserAttributesMappedEvent::class)]
#[CoversClass(SamlUserProvisionedEvent::class)]
#[CoversClass(SamlUserUpdatedEvent::class)]
#[CoversClass(SamlUserProvisionFailedEvent::class)]
final class SamlEventsTest extends TestCase
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
    public function responseReceivedEventCarriesNameIdAttributesAndSessionIndex(): void
    {
        $event = new SamlResponseReceivedEvent(
            nameId: 'alice@example.com',
            attributes: ['email' => ['alice@example.com']],
            sessionIndex: 'session-1',
        );

        self::assertSame('alice@example.com', $event->getNameId());
        self::assertSame(['email' => ['alice@example.com']], $event->getAttributes());
        self::assertSame('session-1', $event->getSessionIndex());
    }

    #[Test]
    public function responseReceivedEventAllowsNullSessionIndex(): void
    {
        $event = new SamlResponseReceivedEvent('alice', [], null);

        self::assertNull($event->getSessionIndex());
    }

    #[Test]
    public function userAttributesMappedEventAllowsListenersToMutateUserdataAndMeta(): void
    {
        $event = new SamlUserAttributesMappedEvent(
            userdata: ['user_login' => 'alice'],
            userMeta: ['_saml_key' => 'val'],
            attributes: ['email' => ['alice@example.com']],
            nameId: 'alice',
            isNewUser: true,
        );

        self::assertSame(['user_login' => 'alice'], $event->getUserdata());
        self::assertSame(['_saml_key' => 'val'], $event->getUserMeta());
        self::assertSame(['email' => ['alice@example.com']], $event->getAttributes());
        self::assertSame('alice', $event->getNameId());
        self::assertTrue($event->isNewUser());

        $event->setUserdata(['user_login' => 'alice', 'display_name' => 'Alice']);
        $event->setUserMeta(['_saml_key' => 'val2']);

        self::assertSame(['user_login' => 'alice', 'display_name' => 'Alice'], $event->getUserdata());
        self::assertSame(['_saml_key' => 'val2'], $event->getUserMeta());
        // Attributes / nameId / isNewUser are readonly — they must survive listener mutations.
        self::assertSame('alice', $event->getNameId());
        self::assertTrue($event->isNewUser());
    }

    #[Test]
    public function userProvisionedEventCarriesUserNameIdAttributes(): void
    {
        $user = $this->makeUser('saml_prov');
        $event = new SamlUserProvisionedEvent($user, 'alice', ['email' => ['alice@example.com']]);

        self::assertSame($user, $event->getUser());
        self::assertSame('alice', $event->getNameId());
        self::assertSame(['email' => ['alice@example.com']], $event->getAttributes());
    }

    #[Test]
    public function userUpdatedEventCarriesUserAndAttributes(): void
    {
        $user = $this->makeUser('saml_upd');
        $event = new SamlUserUpdatedEvent($user, ['role' => ['editor']]);

        self::assertSame($user, $event->getUser());
        self::assertSame(['role' => ['editor']], $event->getAttributes());
    }

    #[Test]
    public function userProvisionFailedEventCarriesNameIdAndError(): void
    {
        $error = new \WP_Error('saml_provision_failed', 'detail');
        $event = new SamlUserProvisionFailedEvent('alice', $error);

        self::assertSame('alice', $event->getNameId());
        self::assertSame($error, $event->getError());
    }
}
