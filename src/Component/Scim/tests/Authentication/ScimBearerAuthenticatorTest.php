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

namespace WpPack\Component\Scim\Tests\Authentication;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Scim\Authentication\ScimBearerAuthenticator;
use WpPack\Component\Security\Authentication\Token\ServiceToken;
use WpPack\Component\Security\Exception\AuthenticationException;

final class ScimBearerAuthenticatorTest extends TestCase
{
    private ScimBearerAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->authenticator = new ScimBearerAuthenticator(
            bearerToken: 'test-secret-token',
        );
    }

    #[Test]
    public function supportsScimPathWithBearerHeader(): void
    {
        $request = Request::create('/wp-json/scim/v2/Users', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-token',
        ]);

        self::assertTrue($this->authenticator->supports($request));
    }

    #[Test]
    public function doesNotSupportNonScimPath(): void
    {
        $request = Request::create('/wp-json/wp/v2/posts', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-token',
        ]);

        self::assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function doesNotSupportWithoutBearerHeader(): void
    {
        $request = Request::create('/wp-json/scim/v2/Users', 'GET');

        self::assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function authenticateSucceedsWithValidToken(): void
    {
        $request = Request::create('/wp-json/scim/v2/Users', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-token',
        ]);

        $passport = $this->authenticator->authenticate($request);

        self::assertSame('scim-service', $passport->getUserBadge()->getUserIdentifier());
    }

    #[Test]
    public function authenticateThrowsOnInvalidToken(): void
    {
        $request = Request::create('/wp-json/scim/v2/Users', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer wrong-token',
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid bearer token.');

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateThrowsOnMissingAuthorizationHeader(): void
    {
        $request = Request::create('/wp-json/scim/v2/Users', 'GET');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Missing Authorization header.');

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function createTokenReturnsServiceToken(): void
    {
        $request = Request::create('/wp-json/scim/v2/Users', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-token',
        ]);

        $passport = $this->authenticator->authenticate($request);
        $token = $this->authenticator->createToken($passport);

        self::assertInstanceOf(ServiceToken::class, $token);
    }

    #[Test]
    public function createTokenHasScimProvisionCapability(): void
    {
        $request = Request::create('/wp-json/scim/v2/Users', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-token',
        ]);

        $passport = $this->authenticator->authenticate($request);
        $token = $this->authenticator->createToken($passport);

        self::assertInstanceOf(ServiceToken::class, $token);
        self::assertSame(['scim_provision'], $token->getCapabilities());
        self::assertSame('scim-service', $token->getServiceIdentifier());
    }

    #[Test]
    public function createTokenHasNoUser(): void
    {
        $request = Request::create('/wp-json/scim/v2/Users', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-token',
        ]);

        $passport = $this->authenticator->authenticate($request);
        $token = $this->authenticator->createToken($passport);

        self::assertNull($token->getUser());
        self::assertTrue($token->isAuthenticated());
    }

    #[Test]
    public function onAuthenticationSuccessReturnsNull(): void
    {
        $request = Request::create('/wp-json/scim/v2/Users', 'GET');
        $token = new ServiceToken('scim-service', capabilities: ['scim_provision']);

        self::assertNull($this->authenticator->onAuthenticationSuccess($request, $token));
    }

    #[Test]
    public function onAuthenticationFailureReturnsNull(): void
    {
        $request = Request::create('/wp-json/scim/v2/Users', 'GET');
        $exception = new AuthenticationException('Test failure.');

        self::assertNull($this->authenticator->onAuthenticationFailure($request, $exception));
    }
}
