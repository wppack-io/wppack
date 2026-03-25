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
use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Bridge\SAML\Badge\SamlAttributesBadge;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\Multisite\CrossSiteRedirector;
use WpPack\Component\Security\Bridge\SAML\SamlAuthenticator;
use WpPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolverInterface;
use WpPack\Component\Security\Exception\AuthenticationException;

#[CoversClass(SamlAuthenticator::class)]
final class SamlAuthenticatorTest extends TestCase
{
    private SamlAuthFactory $factory;
    private SamlUserResolverInterface $userResolver;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->factory = $this->createMock(SamlAuthFactory::class);
        $this->userResolver = $this->createMock(SamlUserResolverInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    private function createAuthenticator(string $acsPath = '/saml/acs'): SamlAuthenticator
    {
        return new SamlAuthenticator(
            $this->factory,
            $this->userResolver,
            $this->eventDispatcher,
            $acsPath,
        );
    }

    #[Test]
    public function supportsWithValidRequest(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['SAMLResponse' => 'base64encodedresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        self::assertTrue($authenticator->supports($request));
    }

    #[Test]
    public function supportsWithGetRequest(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/acs'],
        );

        self::assertFalse($authenticator->supports($request));
    }

    #[Test]
    public function supportsWithoutSamlResponse(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['action' => 'login'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        self::assertFalse($authenticator->supports($request));
    }

    #[Test]
    public function supportsWithWrongPath(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['SAMLResponse' => 'base64encodedresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login'],
        );

        self::assertFalse($authenticator->supports($request));
    }

    #[Test]
    public function supportsWithSubdirectoryInstall(): void
    {
        $authenticator = $this->createAuthenticator(acsPath: '/wp/saml/acs');

        $request = new Request(
            post: ['SAMLResponse' => 'base64encodedresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/wp/saml/acs'],
        );

        self::assertTrue($authenticator->supports($request));
    }

    #[Test]
    public function authenticate(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->method('processResponse')->willReturn(null);
        $auth->method('getErrors')->willReturn([]);
        $auth->method('getNameId')->willReturn('user@example.com');
        $auth->method('getAttributes')->willReturn(['email' => ['user@example.com']]);
        $auth->method('getSessionIndex')->willReturn('_session123');

        $this->factory->method('create')->willReturn($auth);

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['SAMLResponse' => 'base64encodedresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        self::assertTrue($passport->hasBadge(UserBadge::class));
        self::assertTrue($passport->hasBadge(SamlAttributesBadge::class));

        $samlBadge = $passport->getBadge(SamlAttributesBadge::class);
        self::assertInstanceOf(SamlAttributesBadge::class, $samlBadge);
        self::assertSame('user@example.com', $samlBadge->getNameId());
        self::assertSame(['email' => ['user@example.com']], $samlBadge->getAttributes());
        self::assertSame('_session123', $samlBadge->getSessionIndex());
    }

    #[Test]
    public function authenticateWithErrors(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->method('processResponse')->willReturn(null);
        $auth->method('getErrors')->willReturn(['invalid_response']);
        $auth->method('getLastErrorReason')->willReturn('Signature validation failed');

        $this->factory->method('create')->willReturn($auth);

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['SAMLResponse' => 'invalidresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('SAML authentication failed.');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateWithErrorsDoesNotLeakDetails(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->method('processResponse')->willReturn(null);
        $auth->method('getErrors')->willReturn(['invalid_response']);
        $auth->method('getLastErrorReason')->willReturn('Signature validation failed. Certificate mismatch.');

        $this->factory->method('create')->willReturn($auth);

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['SAMLResponse' => 'invalidresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        try {
            $authenticator->authenticate($request);
            self::fail('Expected AuthenticationException was not thrown.');
        } catch (AuthenticationException $e) {
            self::assertStringNotContainsString('Signature validation failed', $e->getMessage());
            self::assertStringNotContainsString('Certificate', $e->getMessage());
            self::assertStringNotContainsString('invalid_response', $e->getMessage());
        }
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
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $exception = new AuthenticationException('SAML authentication failed');

        $response = $authenticator->onAuthenticationFailure($request, $exception);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringContainsString('saml_error=1', $response->url);
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
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringContainsString('/wp-admin', $response->url);
    }

    #[Test]
    public function onAuthenticationSuccessIgnoresNonSameOriginRelayState(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['RelayState' => 'https://evil.example.com/phishing'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringNotContainsString('evil.example.com', $response->url);
        self::assertStringContainsString('/wp-admin', $response->url);
    }

    #[Test]
    public function onAuthenticationSuccessWithSameOriginRelayState(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        $authenticator = $this->createAuthenticator();

        $siteUrl = home_url('/custom-page');

        $request = new Request(
            post: ['RelayState' => $siteUrl],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    #[Test]
    public function onAuthenticationSuccessWithoutRelayState(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: [],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringContainsString('/wp-admin', $response->url);
    }

    #[Test]
    public function onAuthenticationSuccessWithInvalidRelayStateUrl(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        $authenticator = $this->createAuthenticator();

        // RelayState with no host
        $request = new Request(
            post: ['RelayState' => '/relative/path'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
        // Should fall back to admin_url()
        self::assertStringContainsString('/wp-admin', $response->url);
    }

    #[Test]
    public function authenticateDispatchesEvent(): void
    {
        $auth = $this->createMock(\OneLogin\Saml2\Auth::class);
        $auth->method('processResponse')->willReturn(null);
        $auth->method('getErrors')->willReturn([]);
        $auth->method('getNameId')->willReturn('user@example.com');
        $auth->method('getAttributes')->willReturn(['email' => ['user@example.com']]);
        $auth->method('getSessionIndex')->willReturn('_session456');

        $this->factory->method('create')->willReturn($auth);

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch');

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['SAMLResponse' => 'base64encodedresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateWithCrossSiteRedirectorNoRedirectNeeded(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->method('processResponse')->willReturn(null);
        $auth->method('getErrors')->willReturn([]);
        $auth->method('getNameId')->willReturn('user@example.com');
        $auth->method('getAttributes')->willReturn(['email' => ['user@example.com']]);
        $auth->method('getSessionIndex')->willReturn('_session');

        $this->factory->method('create')->willReturn($auth);

        // CrossSiteRedirector is final, use a real instance
        // needsRedirect returns false for same-host URLs
        $crossSiteRedirector = new CrossSiteRedirector();

        $authenticator = new SamlAuthenticator(
            $this->factory,
            $this->userResolver,
            $this->eventDispatcher,
            '/saml/acs',
            $crossSiteRedirector,
        );

        // Use a same-site relay state so needsRedirect returns false
        $sameHostUrl = site_url('/custom-page');

        $request = new Request(
            post: ['SAMLResponse' => 'base64response', 'RelayState' => $sameHostUrl],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    #[Test]
    public function authenticateWithCrossSiteRedirectorWithNullRelayState(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->method('processResponse')->willReturn(null);
        $auth->method('getErrors')->willReturn([]);
        $auth->method('getNameId')->willReturn('user@example.com');
        $auth->method('getAttributes')->willReturn(['email' => ['user@example.com']]);
        $auth->method('getSessionIndex')->willReturn('_session');

        $this->factory->method('create')->willReturn($auth);

        // CrossSiteRedirector is final, use a real instance
        $crossSiteRedirector = new CrossSiteRedirector();

        $authenticator = new SamlAuthenticator(
            $this->factory,
            $this->userResolver,
            $this->eventDispatcher,
            '/saml/acs',
            $crossSiteRedirector,
        );

        $request = new Request(
            post: ['SAMLResponse' => 'base64response'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    #[Test]
    public function createTokenWithCrossSiteRedirectorOnNonMultisite(): void
    {
        if (is_multisite()) {
            self::markTestSkipped('This test requires a non-multisite installation.');
        }

        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $crossSiteRedirector = new CrossSiteRedirector();

        $authenticator = new SamlAuthenticator(
            $this->factory,
            $this->userResolver,
            $this->eventDispatcher,
            '/saml/acs',
            $crossSiteRedirector,
        );

        $userBadge = new UserBadge('user@example.com', fn() => $user);
        $passport = new SelfValidatingPassport($userBadge);

        $token = $authenticator->createToken($passport);

        self::assertInstanceOf(PostAuthenticationToken::class, $token);
        // On non-multisite, blogId should be null even with crossSiteRedirector
        self::assertNull($token->getBlogId());
    }

    #[Test]
    public function createTokenWithoutCrossSiteRedirector(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['editor'];

        // No crossSiteRedirector provided
        $authenticator = $this->createAuthenticator();

        $userBadge = new UserBadge('user@example.com', fn() => $user);
        $passport = new SelfValidatingPassport($userBadge);

        $token = $authenticator->createToken($passport);

        self::assertInstanceOf(PostAuthenticationToken::class, $token);
        // blogId should be null when no crossSiteRedirector
        self::assertNull($token->getBlogId());
    }

    #[Test]
    public function onAuthenticationSuccessWithUserAlreadyMemberOfBlog(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        // Use the default authenticator (addUserToBlog = true)
        $authenticator = new SamlAuthenticator(
            $this->factory,
            $this->userResolver,
            $this->eventDispatcher,
            '/saml/acs',
            null,
            true,
        );

        $request = new Request(
            post: [],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    #[Test]
    public function onAuthenticationSuccessWithAddUserToBlogDisabled(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        $authenticator = new SamlAuthenticator(
            $this->factory,
            $this->userResolver,
            $this->eventDispatcher,
            '/saml/acs',
            null,
            false,
        );

        $request = new Request(
            post: [],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringContainsString('/wp-admin', $response->url);
    }

    #[Test]
    public function authenticateWithNullSessionIndex(): void
    {
        $auth = $this->createMock(\OneLogin\Saml2\Auth::class);
        $auth->method('processResponse')->willReturn(null);
        $auth->method('getErrors')->willReturn([]);
        $auth->method('getNameId')->willReturn('user@example.com');
        $auth->method('getAttributes')->willReturn([]);
        $auth->method('getSessionIndex')->willReturn(null);

        $this->factory->method('create')->willReturn($auth);

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['SAMLResponse' => 'base64encodedresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);

        $samlBadge = $passport->getBadge(SamlAttributesBadge::class);
        self::assertInstanceOf(SamlAttributesBadge::class, $samlBadge);
        self::assertNull($samlBadge->getSessionIndex());
    }
}
