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
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\AuthenticationSession;
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

        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());

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

        $handler = new SamlLogoutHandler($factory, new AuthenticationSession(), 'https://sp.example.com/default-redirect');

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

        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());

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

        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());

        $request = new Request(
            query: ['SAMLRequest' => 'encoded-request', 'RelayState' => 'https://sp.example.com/'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $handler->handleIdpLogoutRequest($request);
    }

    #[Test]
    public function handleIdpLogoutRequestRestoresGetAfterProcessing(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->method('processSLO');

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());

        // Simulate wp_magic_quotes() having slashed $_GET
        $originalGet = $_GET;
        $_GET = ['SAMLRequest' => 'value\\with\\slashes', 'existing' => 'param'];

        $request = new Request(
            query: ['SAMLRequest' => 'clean-value', 'RelayState' => 'https://sp.example.com/'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        try {
            $handler->handleIdpLogoutRequest($request);
        } finally {
            // $_GET should be restored to the original slashed state
            self::assertSame('value\\with\\slashes', $_GET['SAMLRequest']);
            self::assertSame('param', $_GET['existing']);
            $_GET = $originalGet;
        }
    }

    #[Test]
    public function isLogoutRequestReturnsTrueWhenSamlRequestPresent(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());
        $request = Request::create('/saml/slo', 'GET', ['SAMLRequest' => 'encoded-request']);

        self::assertTrue($handler->isLogoutRequest($request));
    }

    #[Test]
    public function isLogoutRequestReturnsFalseWhenNoSamlRequest(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());
        $request = Request::create('/saml/slo');

        self::assertFalse($handler->isLogoutRequest($request));
    }

    #[Test]
    public function isLogoutResponseReturnsTrueWhenSamlResponsePresent(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());
        $request = Request::create('/saml/slo', 'GET', ['SAMLResponse' => 'encoded-response']);

        self::assertTrue($handler->isLogoutResponse($request));
    }

    #[Test]
    public function isLogoutResponseReturnsFalseWhenNoSamlResponse(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());
        $request = Request::create('/saml/slo');

        self::assertFalse($handler->isLogoutResponse($request));
    }

    #[Test]
    public function isLogoutRequestAndResponseAreIndependent(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());
        $request = Request::create('/saml/slo', 'GET', ['SAMLRequest' => 'request']);

        self::assertTrue($handler->isLogoutRequest($request));
        self::assertFalse($handler->isLogoutResponse($request));
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

        $handler = new SamlLogoutHandler($factory, new AuthenticationSession(), 'https://sp.example.com/default');

        try {
            $handler->initiateLogout('user@example.com', '_session789', 'https://sp.example.com/custom');
        } catch (\Throwable) {
            // logout() may call exit internally
        }
    }
}
