<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Security\Tests\Authentication\Authenticator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Security\Authentication\Authenticator\FormLoginAuthenticator;
use WPPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;
use WPPack\Component\Security\Authentication\Passport\Badge\RememberMeBadge;
use WPPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WPPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WPPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WPPack\Component\Security\Exception\AuthenticationException;
use WPPack\Component\Site\BlogContext;

final class FormLoginAuthenticatorTest extends TestCase
{
    private FormLoginAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->authenticator = new FormLoginAuthenticator(new BlogContext());
    }

    #[Test]
    public function supportsPostWithLogAndPwd(): void
    {
        $request = new Request(
            post: ['log' => 'admin', 'pwd' => 'secret'],
            server: ['REQUEST_METHOD' => 'POST'],
        );

        self::assertTrue($this->authenticator->supports($request));
    }

    #[Test]
    public function doesNotSupportGetRequest(): void
    {
        $request = new Request(
            post: ['log' => 'admin', 'pwd' => 'secret'],
            server: ['REQUEST_METHOD' => 'GET'],
        );

        self::assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function doesNotSupportPostWithoutCredentials(): void
    {
        $request = new Request(
            post: ['action' => 'login'],
            server: ['REQUEST_METHOD' => 'POST'],
        );

        self::assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function doesNotSupportPostWithOnlyLog(): void
    {
        $request = new Request(
            post: ['log' => 'admin'],
            server: ['REQUEST_METHOD' => 'POST'],
        );

        self::assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function doesNotSupportPostWithOnlyPwd(): void
    {
        $request = new Request(
            post: ['pwd' => 'secret'],
            server: ['REQUEST_METHOD' => 'POST'],
        );

        self::assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function authenticateCreatesPassportWithCredentials(): void
    {
        $request = new Request(
            post: ['log' => 'admin', 'pwd' => 'secret123'],
            server: ['REQUEST_METHOD' => 'POST'],
        );

        $passport = $this->authenticator->authenticate($request);

        self::assertTrue($passport->hasBadge(UserBadge::class));
        self::assertTrue($passport->hasBadge(CredentialsBadge::class));

        $userBadge = $passport->getBadge(UserBadge::class);
        self::assertInstanceOf(UserBadge::class, $userBadge);
        self::assertSame('admin', $userBadge->getUserIdentifier());

        $credentialsBadge = $passport->getBadge(CredentialsBadge::class);
        self::assertInstanceOf(CredentialsBadge::class, $credentialsBadge);
        self::assertSame('secret123', $credentialsBadge->getPassword());
    }

    #[Test]
    public function authenticateWithRememberMeCreatesRememberMeBadge(): void
    {
        $request = new Request(
            post: ['log' => 'admin', 'pwd' => 'secret123', 'rememberme' => '1'],
            server: ['REQUEST_METHOD' => 'POST'],
        );

        $passport = $this->authenticator->authenticate($request);

        self::assertTrue($passport->hasBadge(RememberMeBadge::class));

        $rememberMeBadge = $passport->getBadge(RememberMeBadge::class);
        self::assertInstanceOf(RememberMeBadge::class, $rememberMeBadge);
        self::assertTrue($rememberMeBadge->isEnabled());
    }

    #[Test]
    public function authenticateWithoutRememberMeDoesNotAddBadge(): void
    {
        $request = new Request(
            post: ['log' => 'admin', 'pwd' => 'secret123'],
            server: ['REQUEST_METHOD' => 'POST'],
        );

        $passport = $this->authenticator->authenticate($request);

        self::assertFalse($passport->hasBadge(RememberMeBadge::class));
    }

    #[Test]
    public function createTokenReturnsPostAuthenticationToken(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'form_token_test_' . uniqid(),
            'user_email' => 'form_token_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'contributor',
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
            self::assertContains('contributor', $token->getRoles());
            self::assertSame(get_current_blog_id(), $token->getBlogId());
        } finally {
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function onAuthenticationSuccessReturnsNull(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'POST']);
        $user = $this->createMock(\WP_User::class);
        $user->roles = ['subscriber'];
        $token = new PostAuthenticationToken($user, ['subscriber']);

        self::assertNull($this->authenticator->onAuthenticationSuccess($request, $token));
    }

    #[Test]
    public function onAuthenticationFailureReturnsNull(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'POST']);
        $exception = new AuthenticationException('test');

        self::assertNull($this->authenticator->onAuthenticationFailure($request, $exception));
    }
}
