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

namespace WpPack\Component\Security\Tests\Authentication\Authenticator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\Authenticator\ApplicationPasswordAuthenticator;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Exception\AuthenticationException;
use WpPack\Component\Site\BlogContext;

final class ApplicationPasswordAuthenticatorTest extends TestCase
{
    private ApplicationPasswordAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->authenticator = new ApplicationPasswordAuthenticator(new BlogContext());
    }

    /**
     * Ensures wp_authenticate_application_password() treats the request
     * as an API request and recognizes application passwords as in use.
     */
    private function ensureApplicationPasswordsEnabled(): void
    {
        // REST_REQUEST must be defined for wp_authenticate_application_password()
        // to process the credentials instead of short-circuiting.
        if (!\defined('REST_REQUEST')) {
            \define('REST_REQUEST', true);
        }

        // Mark application passwords as "in use" so is_in_use() returns true.
        $networkId = get_main_network_id();
        update_network_option($networkId, \WP_Application_Passwords::OPTION_KEY_IN_USE, true);
    }

    #[Test]
    public function supportsRequestWithBasicAuthAndRestApi(): void
    {
        $credentials = base64_encode('admin:xxxx xxxx xxxx xxxx');

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/wp-json/wp/v2/posts',
                'HTTP_AUTHORIZATION' => 'Basic ' . $credentials,
            ],
        );

        self::assertTrue($this->authenticator->supports($request));
    }

    #[Test]
    public function doesNotSupportWithoutBasicAuth(): void
    {
        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/wp-json/wp/v2/posts',
            ],
        );

        self::assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function doesNotSupportNonRestRequest(): void
    {
        $credentials = base64_encode('admin:xxxx xxxx xxxx xxxx');

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/wp-admin/',
                'HTTP_AUTHORIZATION' => 'Basic ' . $credentials,
            ],
        );

        self::assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function doesNotSupportBearerAuth(): void
    {
        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/wp-json/wp/v2/posts',
                'HTTP_AUTHORIZATION' => 'Bearer some-token',
            ],
        );

        self::assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function authenticateThrowsForInvalidBase64(): void
    {
        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/wp-json/wp/v2/posts',
                'HTTP_AUTHORIZATION' => 'Basic !!!invalid-base64!!!',
            ],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid Basic authentication header.');

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateThrowsForMissingColonSeparator(): void
    {
        // base64 encoded string without colon
        $credentials = base64_encode('no-colon-in-this-string');

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/wp-json/wp/v2/posts',
                'HTTP_AUTHORIZATION' => 'Basic ' . $credentials,
            ],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid Basic authentication header.');

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateThrowsForInvalidApplicationPassword(): void
    {
        $this->ensureApplicationPasswordsEnabled();

        $credentials = base64_encode('nonexistent_user:xxxx xxxx xxxx xxxx');

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/wp-json/wp/v2/posts',
                'HTTP_AUTHORIZATION' => 'Basic ' . $credentials,
            ],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid application password.');

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateReturnsPassportForValidAppPassword(): void
    {
        $this->ensureApplicationPasswordsEnabled();

        $login = 'apppass_test_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'apppass_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        try {
            // Create an application password for the user
            $result = \WP_Application_Passwords::create_new_application_password(
                $userId,
                ['name' => 'Test App Password'],
            );

            self::assertIsArray($result);
            /** @var array{0: string, 1: array<string, mixed>} $result */
            $appPassword = $result[0];

            $credentials = base64_encode($login . ':' . $appPassword);

            $request = new Request(
                server: [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/wp-json/wp/v2/posts',
                    'HTTP_AUTHORIZATION' => 'Basic ' . $credentials,
                ],
            );

            $passport = $this->authenticator->authenticate($request);

            self::assertInstanceOf(SelfValidatingPassport::class, $passport);
            self::assertTrue($passport->hasBadge(UserBadge::class));

            $userBadge = $passport->getBadge(UserBadge::class);
            self::assertInstanceOf(UserBadge::class, $userBadge);
            self::assertSame($login, $userBadge->getUserIdentifier());

            $user = $passport->getUser();
            self::assertInstanceOf(\WP_User::class, $user);
            self::assertSame($userId, $user->ID);
        } finally {
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function createTokenReturnsPostAuthenticationToken(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'apppass_token_' . uniqid(),
            'user_email' => 'apppass_token_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'author',
        ]);

        self::assertIsInt($userId);

        try {
            $user = get_user_by('id', $userId);
            self::assertInstanceOf(\WP_User::class, $user);

            $userBadge = new UserBadge($user->user_login, static fn() => $user);
            $passport = new SelfValidatingPassport($userBadge);

            $token = $this->authenticator->createToken($passport);

            self::assertInstanceOf(PostAuthenticationToken::class, $token);
            self::assertSame($user, $token->getUser());
            self::assertContains('author', $token->getRoles());
            self::assertSame(get_current_blog_id(), $token->getBlogId());
        } finally {
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function onAuthenticationSuccessReturnsNull(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $user = $this->createMock(\WP_User::class);
        $user->roles = ['subscriber'];
        $token = new PostAuthenticationToken($user, ['subscriber']);

        self::assertNull($this->authenticator->onAuthenticationSuccess($request, $token));
    }

    #[Test]
    public function onAuthenticationFailureReturnsNull(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $exception = new AuthenticationException('test');

        self::assertNull($this->authenticator->onAuthenticationFailure($request, $exception));
    }
}
