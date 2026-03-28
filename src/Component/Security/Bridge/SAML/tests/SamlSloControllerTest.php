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

use LightSaml\Binding\AbstractBinding;
use LightSaml\Binding\BindingFactory;
use LightSaml\Context\Profile\MessageContext;
use LightSaml\Model\Protocol\LogoutRequest;
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
use WpPack\Component\Site\BlogContext;
use WpPack\Component\User\UserRepository;

#[CoversClass(SamlSloController::class)]
final class SamlSloControllerTest extends TestCase
{
    private SamlLogoutHandler $logoutHandler;
    private SamlSessionManager $sessionManager;
    private AuthenticationSession $authSession;

    protected function setUp(): void
    {
        $binding = $this->createMock(AbstractBinding::class);
        $binding->method('receive')
            ->willReturnCallback(function ($request, MessageContext $messageContext): void {
                $messageContext->setMessage(new LogoutRequest());
            });

        $bindingFactory = $this->createMock(BindingFactory::class);
        $bindingFactory->method('getBindingByRequest')->willReturn($binding);

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('createBindingFactory')->willReturn($bindingFactory);

        $this->authSession = new AuthenticationSession();
        $this->logoutHandler = new SamlLogoutHandler($factory, $this->authSession);
        $this->sessionManager = new SamlSessionManager(new UserRepository());
    }

    #[Test]
    public function invokeHandlesLogoutRequest(): void
    {
        $request = new Request(
            query: ['SAMLRequest' => 'encoded-request'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request, new BlogContext());
        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(home_url(), $response->url);
    }

    #[Test]
    public function invokeHandlesLogoutResponse(): void
    {
        $request = new Request(
            query: ['SAMLResponse' => 'encoded-response'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request, new BlogContext());
        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(home_url(), $response->url);
    }

    #[Test]
    public function resolvePostLogoutRedirectUsesSameHostRelayState(): void
    {
        $relayState = home_url('/custom-page');

        $request = new Request(
            query: ['SAMLResponse' => 'encoded-response', 'RelayState' => $relayState],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request, new BlogContext());
        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame($relayState, $response->url);
    }

    #[Test]
    public function resolvePostLogoutRedirectFallsBackForUnknownHost(): void
    {
        $relayState = 'https://unknown.example.com/wp-admin/';

        $request = new Request(
            query: ['SAMLResponse' => 'encoded-response', 'RelayState' => $relayState],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request, new BlogContext());
        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(home_url(), $response->url);
    }

    #[Test]
    public function resolvePostLogoutRedirectRejectsInvalidScheme(): void
    {
        $relayState = 'javascript:alert(1)';

        $request = new Request(
            query: ['SAMLResponse' => 'encoded-response', 'RelayState' => $relayState],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request, new BlogContext());
        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(home_url(), $response->url);
    }

    #[Test]
    public function invokeReturnsBadRequestForUnknownRequest(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request, new BlogContext());
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

        $request = new Request(
            query: ['SAMLRequest' => 'encoded-request'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request, new BlogContext());
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

        $request = new Request(
            query: ['SAMLResponse' => 'encoded-response'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $this->sessionManager, $this->authSession, $request, new BlogContext());
        $controller();

        self::assertNull($this->sessionManager->getNameId($userId));
        self::assertNull($this->sessionManager->getSessionIndex($userId));
    }
}
