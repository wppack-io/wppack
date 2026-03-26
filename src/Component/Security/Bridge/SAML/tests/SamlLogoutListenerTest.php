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

namespace WpPack\Component\Security\Bridge\SAML\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\SamlLogoutHandler;
use WpPack\Component\Security\Bridge\SAML\SamlLogoutListener;
use WpPack\Component\Security\Bridge\SAML\Session\SamlSessionManager;

#[CoversClass(SamlLogoutListener::class)]
final class SamlLogoutListenerTest extends TestCase
{
    private int $userId;
    private SamlSessionManager $sessionManager;

    protected function setUp(): void
    {
        $this->userId = (int) wp_insert_user([
            'user_login' => 'saml_logout_test_' . wp_generate_password(8, false),
            'user_pass' => wp_generate_password(),
            'user_email' => 'saml_logout_' . wp_generate_password(8, false) . '@example.com',
        ]);

        $this->sessionManager = new SamlSessionManager();
    }

    protected function tearDown(): void
    {
        wp_delete_user($this->userId);
    }

    #[Test]
    public function onLogoutReturnsEarlyWhenNoNameId(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->expects(self::never())->method('create');

        $handler = new SamlLogoutHandler($factory);
        $listener = new SamlLogoutListener($handler, $this->sessionManager);

        // No SAML session saved for this user
        $listener->onLogout($this->userId);

        // Should not throw or try to create SAML auth
        self::assertNull($this->sessionManager->getNameId($this->userId));
    }

    #[Test]
    public function onLogoutClearsSessionData(): void
    {
        $this->sessionManager->save($this->userId, 'user@example.com', '_session123');

        $factory = $this->createMock(SamlAuthFactory::class);
        // initiateLogout will be called but we let it throw to prevent redirect/exit
        $handler = new SamlLogoutHandler($factory);
        $listener = new SamlLogoutListener($handler, $this->sessionManager);

        try {
            $listener->onLogout($this->userId);
        } catch (\Throwable) {
            // initiateLogout may throw due to mocked factory
        }

        // Session data should be cleared regardless
        self::assertNull($this->sessionManager->getNameId($this->userId));
        self::assertNull($this->sessionManager->getSessionIndex($this->userId));
    }
}
