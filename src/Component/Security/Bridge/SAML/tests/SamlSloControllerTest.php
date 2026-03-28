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
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\SamlLogoutHandler;
use WpPack\Component\Security\Bridge\SAML\SamlSloController;
use WpPack\Component\Security\Bridge\SAML\Session\SamlSessionManager;
use WpPack\Component\User\UserRepository;

#[CoversClass(SamlSloController::class)]
final class SamlSloControllerTest extends TestCase
{
    private SamlLogoutHandler $logoutHandler;
    private SamlSessionManager $sessionManager;
    private AuthenticationSession $authSession;

    /** @var array<string, string> */
    private array $originalGet;

    protected function setUp(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->method('processSLO');

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $this->authSession = new AuthenticationSession();
        $this->logoutHandler = new SamlLogoutHandler($factory, $this->authSession);
        $this->sessionManager = new SamlSessionManager(new UserRepository());

        $this->originalGet = $_GET;
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
    }

    #[Test]
    public function invokeHandlesLogoutRequest(): void
    {
        $_GET = ['SAMLRequest' => 'encoded-request'];

        $request = new Request(
            query: ['SAMLRequest' => 'encoded-request'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request);
        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(home_url(), $response->url);
    }

    #[Test]
    public function invokeHandlesLogoutResponse(): void
    {
        $_GET = ['SAMLResponse' => 'encoded-response'];

        $request = new Request(
            query: ['SAMLResponse' => 'encoded-response'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request);
        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(home_url(), $response->url);
    }

    #[Test]
    public function resolvePostLogoutRedirectUsesSameHostRelayState(): void
    {
        $relayState = home_url('/custom-page');
        $_GET = ['SAMLResponse' => 'encoded-response', 'RelayState' => $relayState];

        $request = new Request(
            query: ['SAMLResponse' => 'encoded-response', 'RelayState' => $relayState],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request);
        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame($relayState, $response->url);
    }

    #[Test]
    public function resolvePostLogoutRedirectFallsBackForUnknownHost(): void
    {
        $relayState = 'https://unknown.example.com/wp-admin/';
        $_GET = ['SAMLResponse' => 'encoded-response', 'RelayState' => $relayState];

        $request = new Request(
            query: ['SAMLResponse' => 'encoded-response', 'RelayState' => $relayState],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request);
        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(home_url(), $response->url);
    }

    #[Test]
    public function resolvePostLogoutRedirectRejectsInvalidScheme(): void
    {
        $relayState = 'javascript:alert(1)';
        $_GET = ['SAMLResponse' => 'encoded-response', 'RelayState' => $relayState];

        $request = new Request(
            query: ['SAMLResponse' => 'encoded-response', 'RelayState' => $relayState],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request);
        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(home_url(), $response->url);
    }

    #[Test]
    public function invokeReturnsBadRequestForUnknownRequest(): void
    {
        $_GET = [];

        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request);
        $response = $controller();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function invokeClearsSamlSessionBeforeLogoutRequest(): void
    {
        $userId = (int) wp_insert_user([
            'user_login' => 'slo_request_test_' . wp_generate_password(6, false),
            'user_pass' => wp_generate_password(),
            'user_email' => 'slo_request_test@example.com',
        ]);
        wp_set_current_user($userId);

        $this->sessionManager->save($userId, 'user@example.com', '_session_idx');

        $_GET = ['SAMLRequest' => 'encoded-request'];

        $request = new Request(
            query: ['SAMLRequest' => 'encoded-request'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request);
        $controller();

        self::assertNull($this->sessionManager->getNameId($userId));
        self::assertNull($this->sessionManager->getSessionIndex($userId));
    }

    #[Test]
    public function invokeClearsSamlSessionBeforeLogoutResponse(): void
    {
        $userId = (int) wp_insert_user([
            'user_login' => 'slo_response_test_' . wp_generate_password(6, false),
            'user_pass' => wp_generate_password(),
            'user_email' => 'slo_response_test@example.com',
        ]);
        wp_set_current_user($userId);

        $this->sessionManager->save($userId, 'user@example.com', '_session_idx');

        $_GET = ['SAMLResponse' => 'encoded-response'];

        $request = new Request(
            query: ['SAMLResponse' => 'encoded-response'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request);
        $controller();

        self::assertNull($this->sessionManager->getNameId($userId));
        self::assertNull($this->sessionManager->getSessionIndex($userId));
    }
}
