<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WpPack\Component\Security\Bridge\OAuth\Multisite\CrossSiteRedirector;
use WpPack\Component\Security\Bridge\OAuth\OAuthAuthenticator;
use WpPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;
use WpPack\Component\Security\Bridge\OAuth\State\OAuthStateStore;
use WpPack\Component\Security\Bridge\OAuth\Token\TokenExchanger;
use WpPack\Component\Security\Bridge\OAuth\UserResolution\OAuthUserResolverInterface;
use WpPack\Component\Security\Exception\AuthenticationException;

#[CoversClass(OAuthAuthenticator::class)]
final class OAuthAuthenticatorTest extends TestCase
{
    private ProviderInterface $provider;
    private OAuthStateStore $stateStore;
    private OAuthUserResolverInterface $userResolver;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->provider = $this->createMock(ProviderInterface::class);
        $this->stateStore = new OAuthStateStore();
        $this->userResolver = $this->createMock(OAuthUserResolverInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    private function createConfiguration(): OAuthConfiguration
    {
        return new OAuthConfiguration(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            redirectUri: 'https://example.com/oauth/callback',
        );
    }

    private function createAuthenticator(
        string $callbackPath = '/oauth/callback',
        string $verifyPath = '/oauth/verify',
        ?CrossSiteRedirector $crossSiteRedirector = null,
    ): OAuthAuthenticator {
        return new OAuthAuthenticator(
            $this->provider,
            $this->createConfiguration(),
            $this->stateStore,
            new TokenExchanger(new \WpPack\Component\HttpClient\HttpClient()),
            $this->userResolver,
            $this->eventDispatcher,
            $callbackPath,
            crossSiteRedirector: $crossSiteRedirector,
            verifyPath: $verifyPath,
        );
    }

    #[Test]
    public function supportsWithValidGetCallback(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            query: ['code' => 'auth-code', 'state' => 'random-state'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        self::assertTrue($authenticator->supports($request));
    }

    #[Test]
    public function supportsWithPostCrossSiteToken(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['_wppack_oauth_token' => 'cross-site-token'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/oauth/verify'],
        );

        self::assertTrue($authenticator->supports($request));
    }

    #[Test]
    public function supportsReturnsFalseForWrongPath(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            query: ['code' => 'auth-code', 'state' => 'random-state'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/login'],
        );

        self::assertFalse($authenticator->supports($request));
    }

    #[Test]
    public function supportsReturnsFalseForGetWithoutCode(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            query: ['state' => 'random-state'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        self::assertFalse($authenticator->supports($request));
    }

    #[Test]
    public function supportsReturnsFalseForGetWithoutState(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            query: ['code' => 'auth-code'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        self::assertFalse($authenticator->supports($request));
    }

    #[Test]
    public function supportsWithCustomPaths(): void
    {
        $authenticator = $this->createAuthenticator(
            callbackPath: '/wp/oauth/callback',
            verifyPath: '/wp/oauth/verify',
        );

        $request = new Request(
            query: ['code' => 'auth-code', 'state' => 'random-state'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/wp/oauth/callback'],
        );

        self::assertTrue($authenticator->supports($request));
    }

    #[Test]
    public function authenticateWithErrorResponse(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            query: ['error' => 'access_denied', 'error_description' => 'User denied'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('OAuth authentication failed.');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateWithErrorDoesNotLeakDetails(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            query: ['error' => 'invalid_grant', 'error_description' => 'Authorization code expired'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        try {
            $authenticator->authenticate($request);
            self::fail('Expected AuthenticationException was not thrown.');
        } catch (AuthenticationException $e) {
            self::assertStringNotContainsString('invalid_grant', $e->getMessage());
            self::assertStringNotContainsString('expired', $e->getMessage());
        }
    }

    #[Test]
    public function authenticateWithInvalidState(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            query: ['code' => 'auth-code', 'state' => 'invalid-state'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('OAuth authentication failed.');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateCrossSiteWithoutRedirector(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['_wppack_oauth_token' => 'some-token'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/oauth/verify'],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('OAuth authentication failed.');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function createToken(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $authenticator = $this->createAuthenticator();

        $userBadge = new UserBadge('user@example.com', fn() => $user);
        $passport = new SelfValidatingPassport($userBadge);

        $token = $authenticator->createToken($passport);

        self::assertInstanceOf(PostAuthenticationToken::class, $token);
    }

    #[Test]
    public function onAuthenticationFailure(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        $exception = new AuthenticationException('OAuth authentication failed');

        $response = $authenticator->onAuthenticationFailure($request, $exception);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringContainsString('oauth_error=1', $response->url);
    }

    #[Test]
    public function onAuthenticationSuccess(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringContainsString('/wp-admin', $response->url);
    }

    #[Test]
    public function authenticateCrossSiteWithValidToken(): void
    {
        $user = get_user_by('id', 1);

        if (!$user instanceof \WP_User) {
            self::markTestSkipped('No user with ID 1 found in test database.');
        }

        $token = 'test-crosssite-auth-token';
        $timestamp = time();
        $payload = $user->ID . '|' . $timestamp . '|' . $token;
        $hmac = wp_hash($payload);

        set_transient(
            '_wppack_oauth_xsite_' . hash('sha256', $token),
            [
                'user_id' => $user->ID,
                'hmac' => $hmac,
                'created_at' => $timestamp,
            ],
            120,
        );

        $crossSiteRedirector = new CrossSiteRedirector(
            allowedHosts: ['example.com'],
            verifyPath: '/oauth/verify',
        );

        $authenticator = $this->createAuthenticator(
            crossSiteRedirector: $crossSiteRedirector,
        );

        $request = new Request(
            post: ['_wppack_oauth_token' => $token],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/oauth/verify'],
        );

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        self::assertSame($user->ID, $passport->getUser()->ID);
    }

    #[Test]
    public function supportsReturnsFalseForPostWithoutToken(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['other_param' => 'value'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/oauth/verify'],
        );

        self::assertFalse($authenticator->supports($request));
    }

    #[Test]
    public function supportsReturnsFalseForDeleteMethod(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            query: ['code' => 'auth-code', 'state' => 'random-state'],
            server: ['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/oauth/callback'],
        );

        self::assertFalse($authenticator->supports($request));
    }

    #[Test]
    public function authenticateWithEmptyCode(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            query: ['code' => '', 'state' => 'some-state'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('OAuth authentication failed.');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateWithEmptyState(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            query: ['code' => 'auth-code', 'state' => ''],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('OAuth authentication failed.');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function onAuthenticationSuccessIgnoresNonSameOriginReturnTo(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['returnTo' => 'https://evil.example.com/phishing'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/oauth/verify'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringNotContainsString('evil.example.com', $response->url);
        self::assertStringContainsString('/wp-admin', $response->url);
    }
}
