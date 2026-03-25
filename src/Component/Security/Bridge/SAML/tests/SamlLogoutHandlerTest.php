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

use OneLogin\Saml2\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\SamlLogoutHandler;

#[CoversClass(SamlLogoutHandler::class)]
final class SamlLogoutHandlerTest extends TestCase
{
    #[Test]
    public function initiateLogoutCallsAuthLogoutWithReturnTo(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->expects(self::once())
            ->method('logout')
            ->with(
                'https://sp.example.com/after-logout',
                [],
                'user@example.com',
                '_session123',
            );

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $handler = new SamlLogoutHandler($factory);

        try {
            $handler->initiateLogout('user@example.com', '_session123', 'https://sp.example.com/after-logout');
        } catch (\Throwable) {
            // logout() may call exit internally
        }
    }

    #[Test]
    public function initiateLogoutUsesRedirectAfterLogoutAsFallback(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->expects(self::once())
            ->method('logout')
            ->with(
                'https://sp.example.com/default-redirect',
                [],
                'user@example.com',
                '_session456',
            );

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $handler = new SamlLogoutHandler($factory, 'https://sp.example.com/default-redirect');

        try {
            $handler->initiateLogout('user@example.com', '_session456');
        } catch (\Throwable) {
            // logout() may call exit internally
        }
    }

    #[Test]
    public function initiateLogoutWithNullReturnToAndNoDefault(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->expects(self::once())
            ->method('logout')
            ->with(
                null,
                [],
                'user@example.com',
                null,
            );

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $handler = new SamlLogoutHandler($factory);

        try {
            $handler->initiateLogout('user@example.com', null);
        } catch (\Throwable) {
            // logout() may call exit internally
        }
    }

    #[Test]
    public function handleIdpLogoutRequestCallsProcessSlo(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->expects(self::once())
            ->method('processSLO');

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $handler = new SamlLogoutHandler($factory);

        $handler->handleIdpLogoutRequest();
    }

    #[Test]
    public function isLogoutRequestReturnsTrueWhenSamlRequestPresent(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory);

        $originalGet = $_GET;
        $_GET['SAMLRequest'] = 'encoded-request';

        try {
            self::assertTrue($handler->isLogoutRequest());
        } finally {
            $_GET = $originalGet;
        }
    }

    #[Test]
    public function isLogoutRequestReturnsFalseWhenNoSamlRequest(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory);

        $originalGet = $_GET;
        unset($_GET['SAMLRequest']);

        try {
            self::assertFalse($handler->isLogoutRequest());
        } finally {
            $_GET = $originalGet;
        }
    }

    #[Test]
    public function isLogoutResponseReturnsTrueWhenSamlResponsePresent(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory);

        $originalGet = $_GET;
        $_GET['SAMLResponse'] = 'encoded-response';

        try {
            self::assertTrue($handler->isLogoutResponse());
        } finally {
            $_GET = $originalGet;
        }
    }

    #[Test]
    public function isLogoutResponseReturnsFalseWhenNoSamlResponse(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory);

        $originalGet = $_GET;
        unset($_GET['SAMLResponse']);

        try {
            self::assertFalse($handler->isLogoutResponse());
        } finally {
            $_GET = $originalGet;
        }
    }

    #[Test]
    public function isLogoutRequestAndResponseAreIndependent(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory);

        $originalGet = $_GET;
        $_GET['SAMLRequest'] = 'request';
        unset($_GET['SAMLResponse']);

        try {
            self::assertTrue($handler->isLogoutRequest());
            self::assertFalse($handler->isLogoutResponse());
        } finally {
            $_GET = $originalGet;
        }
    }

    #[Test]
    public function initiateLogoutReturnToOverridesDefault(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->expects(self::once())
            ->method('logout')
            ->with(
                'https://sp.example.com/custom',
                [],
                'user@example.com',
                '_session789',
            );

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $handler = new SamlLogoutHandler($factory, 'https://sp.example.com/default');

        try {
            $handler->initiateLogout('user@example.com', '_session789', 'https://sp.example.com/custom');
        } catch (\Throwable) {
            // logout() may call exit internally
        }
    }
}
