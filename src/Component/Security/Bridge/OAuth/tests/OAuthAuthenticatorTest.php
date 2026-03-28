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

namespace WpPack\Component\Security\Bridge\OAuth\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Bridge\OAuth\Badge\OAuthTokenBadge;
use WpPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WpPack\Component\Security\Bridge\OAuth\Multisite\CrossSiteRedirector;
use WpPack\Component\Security\Bridge\OAuth\OAuthAuthenticator;
use WpPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;
use WpPack\Component\Security\Bridge\OAuth\State\OAuthStateStore;
use WpPack\Component\Security\Bridge\OAuth\State\StoredState;
use WpPack\Component\Security\Bridge\OAuth\Token\IdTokenValidator;
use WpPack\Component\Security\Bridge\OAuth\Token\JwksProvider;
use WpPack\Component\Security\Bridge\OAuth\Token\OAuthTokenSet;
use WpPack\Component\Security\Bridge\OAuth\Token\TokenExchanger;
use WpPack\Component\Security\Bridge\OAuth\UserResolution\OAuthUserResolverInterface;
use WpPack\Component\Security\Exception\AuthenticationException;
use WpPack\Component\Site\BlogContext;
use WpPack\Component\Site\SiteRepository;
use WpPack\Component\Transient\TransientManager;

#[CoversClass(OAuthAuthenticator::class)]
final class OAuthAuthenticatorTest extends TestCase
{
    private ProviderInterface $provider;
    private OAuthStateStore $stateStore;
    private OAuthUserResolverInterface $userResolver;
    private EventDispatcherInterface $eventDispatcher;

    /** @var array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null */
    private ?array $mockResponse = null;

    protected function setUp(): void
    {
        $this->provider = $this->createMock(ProviderInterface::class);
        $this->stateStore = new OAuthStateStore(new TransientManager());
        $this->userResolver = $this->createMock(OAuthUserResolverInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        add_filter('pre_http_request', [$this, 'mockHttpResponse'], 10, 3);
    }

    protected function tearDown(): void
    {
        remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);
        $this->mockResponse = null;
    }

    /**
     * @param false|array<string, mixed> $response
     * @param array<string, mixed> $parsedArgs
     */
    public function mockHttpResponse(mixed $response, array $parsedArgs, string $url): mixed
    {
        if ($this->mockResponse !== null) {
            return $this->mockResponse;
        }

        return $response;
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
        ?IdTokenValidator $idTokenValidator = null,
        ?JwksProvider $jwksProvider = null,
        ?HttpClient $httpClient = null,
        bool $addUserToBlog = true,
    ): OAuthAuthenticator {
        return new OAuthAuthenticator(
            $this->provider,
            $this->createConfiguration(),
            $this->stateStore,
            new TokenExchanger(new HttpClient()),
            $this->userResolver,
            $this->eventDispatcher,
            new BlogContext(),
            $callbackPath,
            $idTokenValidator,
            $jwksProvider,
            $crossSiteRedirector,
            $httpClient,
            $addUserToBlog,
            $verifyPath,
        );
    }

    private function storeState(string $stateKey, ?string $codeVerifier = null, ?string $returnTo = null): StoredState
    {
        $state = StoredState::create('test-nonce', $codeVerifier, $returnTo);
        $this->stateStore->store($stateKey, $state);

        return $state;
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
            new BlogContext(),
            new SiteRepository(),
            new TransientManager(),
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

    #[Test]
    public function authenticateNonOidcFlowWithUserInfo(): void
    {
        $stateKey = 'test-state-non-oidc';
        $this->storeState($stateKey);

        $user = $this->createMock(\WP_User::class);
        $user->ID = 42;
        $user->roles = ['subscriber'];

        $this->provider->method('getTokenEndpoint')->willReturn('https://github.com/login/oauth/access_token');
        $this->provider->method('supportsOidc')->willReturn(false);
        $this->provider->method('getUserInfoEndpoint')->willReturn('https://api.github.com/user');
        $this->provider->method('normalizeUserInfo')->willReturnCallback(static function (array $data): array {
            $normalized = [];
            if (isset($data['id'])) {
                $normalized['sub'] = (string) $data['id'];
            }
            if (isset($data['email'])) {
                $normalized['email'] = $data['email'];
            }

            return $normalized;
        });

        $this->userResolver->method('resolveUser')->willReturn($user);

        // Mock token exchange response
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'access_token' => 'gho_test_access_token',
                'token_type' => 'Bearer',
            ]),
        ];

        $httpClient = new HttpClient();

        $authenticator = $this->createAuthenticator(
            httpClient: $httpClient,
        );

        // First request will be token exchange, second will be userinfo
        // We need to handle both; after the token exchange the userinfo call happens
        $callCount = 0;
        remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);
        add_filter('pre_http_request', function ($response, $parsedArgs, $url) use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                // Token exchange
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'headers' => ['content-type' => 'application/json'],
                    'body' => json_encode([
                        'access_token' => 'gho_test_access_token',
                        'token_type' => 'Bearer',
                    ]),
                ];
            }

            // Userinfo
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'headers' => ['content-type' => 'application/json'],
                'body' => json_encode([
                    'id' => 12345,
                    'login' => 'testuser',
                    'email' => 'test@example.com',
                ]),
            ];
        }, 10, 3);

        $request = new Request(
            query: ['code' => 'auth-code', 'state' => $stateKey],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        self::assertTrue($passport->hasBadge(OAuthTokenBadge::class));

        remove_all_filters('pre_http_request');
    }

    #[Test]
    public function authenticateOidcFlowWithIdToken(): void
    {
        $stateKey = 'test-state-oidc';
        $this->storeState($stateKey);

        $user = $this->createMock(\WP_User::class);
        $user->ID = 42;
        $user->roles = ['subscriber'];

        $this->provider->method('getTokenEndpoint')->willReturn('https://accounts.google.com/o/oauth2/token');
        $this->provider->method('supportsOidc')->willReturn(true);
        $this->provider->method('getJwksUri')->willReturn('https://www.googleapis.com/oauth2/v3/certs');
        $this->provider->method('getIssuer')->willReturn('https://accounts.google.com');

        $this->userResolver->method('resolveUser')->willReturn($user);

        // Generate a real RSA key pair and valid JWT for the OIDC test
        $resource = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);
        \assert($resource instanceof \OpenSSLAsymmetricKey);

        $details = openssl_pkey_get_details($resource);
        \assert(\is_array($details));

        $kid = 'test-key-1';
        $nonce = 'test-nonce';
        $jwks = [
            [
                'kty' => 'RSA',
                'kid' => $kid,
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
            ],
        ];

        $idTokenPayload = [
            'iss' => 'https://accounts.google.com',
            'aud' => 'test-client-id',
            'sub' => 'google-user-123',
            'exp' => time() + 3600,
            'iat' => time() - 10,
            'nonce' => $nonce,
        ];
        $idToken = \Firebase\JWT\JWT::encode($idTokenPayload, $resource, 'RS256', $kid);

        // Create real IdTokenValidator
        $idTokenValidator = new IdTokenValidator();

        // Create a JwksProvider that returns our test keys via transient cache
        $jwksCacheKey = '_wppack_oauth_jwks_' . md5('https://www.googleapis.com/oauth2/v3/certs');
        set_transient($jwksCacheKey, $jwks, 3600);
        $jwksProvider = new JwksProvider(new HttpClient(), new TransientManager());

        // We need to override the stored state nonce to match
        // Store state with the correct nonce
        $stateKey2 = 'test-state-oidc-real';
        $state = StoredState::create($nonce, null, null);
        $this->stateStore->store($stateKey2, $state);

        // Mock token exchange (returns id_token)
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'access_token' => 'ya29_test',
                'token_type' => 'Bearer',
                'id_token' => $idToken,
            ]),
        ];

        $authenticator = $this->createAuthenticator(
            idTokenValidator: $idTokenValidator,
            jwksProvider: $jwksProvider,
        );

        $request = new Request(
            query: ['code' => 'auth-code', 'state' => $stateKey2],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        self::assertTrue($passport->hasBadge(OAuthTokenBadge::class));

        $badge = $passport->getBadge(UserBadge::class);
        self::assertInstanceOf(UserBadge::class, $badge);
        self::assertSame('google-user-123', $badge->getUserIdentifier());

        // Clean up
        delete_transient($jwksCacheKey);
    }

    #[Test]
    public function authenticateOidcThrowsWhenMissingValidator(): void
    {
        $stateKey = 'test-state-no-validator';
        $this->storeState($stateKey);

        $this->provider->method('getTokenEndpoint')->willReturn('https://accounts.google.com/o/oauth2/token');
        $this->provider->method('supportsOidc')->willReturn(true);

        // Mock token exchange (returns id_token)
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'access_token' => 'ya29_test',
                'token_type' => 'Bearer',
                'id_token' => 'eyJ.test.id_token',
            ]),
        ];

        // No idTokenValidator or jwksProvider provided
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            query: ['code' => 'auth-code', 'state' => $stateKey],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('OAuth authentication failed.');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateNonOidcThrowsForNonHttpsUserInfoEndpoint(): void
    {
        $stateKey = 'test-state-http-userinfo';
        $this->storeState($stateKey);

        $this->provider->method('getTokenEndpoint')->willReturn('https://github.com/login/oauth/access_token');
        $this->provider->method('supportsOidc')->willReturn(false);
        $this->provider->method('getUserInfoEndpoint')->willReturn('http://api.github.com/user');

        // Mock token exchange (no id_token)
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'access_token' => 'gho_test',
                'token_type' => 'Bearer',
            ]),
        ];

        $authenticator = $this->createAuthenticator(
            httpClient: new HttpClient(),
        );

        $request = new Request(
            query: ['code' => 'auth-code', 'state' => $stateKey],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('OAuth authentication failed.');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateNonOidcThrowsWhenSubjectIsEmpty(): void
    {
        $stateKey = 'test-state-empty-sub';
        $this->storeState($stateKey);

        $this->provider->method('getTokenEndpoint')->willReturn('https://github.com/login/oauth/access_token');
        $this->provider->method('supportsOidc')->willReturn(false);
        $this->provider->method('getUserInfoEndpoint')->willReturn(null);

        // Mock token exchange (no id_token)
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'access_token' => 'gho_test',
                'token_type' => 'Bearer',
            ]),
        ];

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            query: ['code' => 'auth-code', 'state' => $stateKey],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('OAuth authentication failed.');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateCrossSiteWithInvalidToken(): void
    {
        $crossSiteRedirector = new CrossSiteRedirector(
            new BlogContext(),
            new SiteRepository(),
            new TransientManager(),
            allowedHosts: ['example.com'],
            verifyPath: '/oauth/verify',
        );

        $authenticator = $this->createAuthenticator(
            crossSiteRedirector: $crossSiteRedirector,
        );

        $request = new Request(
            post: ['_wppack_oauth_token' => 'invalid-token-xyz'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/oauth/verify'],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('OAuth authentication failed.');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateCrossSiteWithNonExistentUser(): void
    {
        $token = 'test-crosssite-nonexistent-user-token';
        $userId = 999999;
        $timestamp = time();
        $payload = $userId . '|' . $timestamp . '|' . $token;
        $hmac = wp_hash($payload);

        set_transient(
            '_wppack_oauth_xsite_' . hash('sha256', $token),
            [
                'user_id' => $userId,
                'hmac' => $hmac,
                'created_at' => $timestamp,
            ],
            120,
        );

        $crossSiteRedirector = new CrossSiteRedirector(
            new BlogContext(),
            new SiteRepository(),
            new TransientManager(),
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

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('OAuth authentication failed.');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function onAuthenticationSuccessWithSameOriginReturnTo(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        $authenticator = $this->createAuthenticator();

        $siteUrl = home_url('/custom-page');

        $request = new Request(
            post: ['returnTo' => $siteUrl],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/oauth/verify'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    #[Test]
    public function onAuthenticationSuccessWithInvalidReturnToUrl(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        $authenticator = $this->createAuthenticator();

        // returnTo with no host
        $request = new Request(
            post: ['returnTo' => '/relative/path'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/oauth/verify'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
        // Should fall back to admin_url()
        self::assertStringContainsString('/wp-admin', $response->url);
    }

    #[Test]
    public function authenticateNonOidcWithNullUserInfoEndpoint(): void
    {
        $stateKey = 'test-state-null-userinfo';
        $this->storeState($stateKey);

        $this->provider->method('getTokenEndpoint')->willReturn('https://custom.example.com/token');
        $this->provider->method('supportsOidc')->willReturn(false);
        $this->provider->method('getUserInfoEndpoint')->willReturn(null);

        // Mock token exchange (no id_token)
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'access_token' => 'test_access_token',
                'token_type' => 'Bearer',
            ]),
        ];

        $authenticator = $this->createAuthenticator(
            httpClient: new HttpClient(),
        );

        $request = new Request(
            query: ['code' => 'auth-code', 'state' => $stateKey],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        // No userinfo endpoint + no id_token => subject will be empty => exception
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('OAuth authentication failed.');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateNonOidcWithoutHttpClient(): void
    {
        $stateKey = 'test-state-no-http-client';
        $this->storeState($stateKey);

        $this->provider->method('getTokenEndpoint')->willReturn('https://custom.example.com/token');
        $this->provider->method('supportsOidc')->willReturn(false);
        $this->provider->method('getUserInfoEndpoint')->willReturn('https://api.example.com/userinfo');

        // Mock token exchange (no id_token)
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'access_token' => 'test_access_token',
                'token_type' => 'Bearer',
            ]),
        ];

        // No httpClient provided => userinfo won't be fetched => empty subject
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            query: ['code' => 'auth-code', 'state' => $stateKey],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('OAuth authentication failed.');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateErrorWithOnlyErrorParam(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            query: ['error' => 'server_error'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('OAuth authentication failed.');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateNonOidcWithCrossSiteRedirectorButSameHost(): void
    {
        $stateKey = 'test-state-xsite-same';
        // Store state with a returnTo that is the same host (needsRedirect returns false)
        $this->storeState($stateKey, null, site_url('/dashboard'));

        $user = $this->createMock(\WP_User::class);
        $user->ID = 42;
        $user->roles = ['subscriber'];

        $this->provider->method('getTokenEndpoint')->willReturn('https://github.com/login/oauth/access_token');
        $this->provider->method('supportsOidc')->willReturn(false);
        $this->provider->method('getUserInfoEndpoint')->willReturn('https://api.github.com/user');
        $this->provider->method('normalizeUserInfo')->willReturnCallback(static function (array $data): array {
            return ['sub' => (string) ($data['id'] ?? '')];
        });

        $this->userResolver->method('resolveUser')->willReturn($user);

        $callCount = 0;
        remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);
        add_filter('pre_http_request', function ($response, $parsedArgs, $url) use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'headers' => ['content-type' => 'application/json'],
                    'body' => json_encode([
                        'access_token' => 'gho_test',
                        'token_type' => 'Bearer',
                    ]),
                ];
            }

            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'headers' => ['content-type' => 'application/json'],
                'body' => json_encode(['id' => 12345]),
            ];
        }, 10, 3);

        $crossSiteRedirector = new CrossSiteRedirector(
            new BlogContext(),
            new SiteRepository(),
            new TransientManager(),
            allowedHosts: ['example.com'],
            verifyPath: '/oauth/verify',
        );

        $authenticator = $this->createAuthenticator(
            httpClient: new HttpClient(),
            crossSiteRedirector: $crossSiteRedirector,
        );

        $request = new Request(
            query: ['code' => 'auth-code', 'state' => $stateKey],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/oauth/callback'],
        );

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);

        remove_all_filters('pre_http_request');
    }

    #[Test]
    public function supportsReturnsFalseForPostTokenWithWrongPath(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['_wppack_oauth_token' => 'some-token'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/wrong/path'],
        );

        self::assertFalse($authenticator->supports($request));
    }

    #[Test]
    public function onAuthenticationSuccessWithoutReturnTo(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['editor'];

        $token = new PostAuthenticationToken($user, ['editor']);

        $authenticator = $this->createAuthenticator();

        // No returnTo in POST
        $request = new Request(
            post: [],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/oauth/verify'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringContainsString('/wp-admin', $response->url);
    }
}
