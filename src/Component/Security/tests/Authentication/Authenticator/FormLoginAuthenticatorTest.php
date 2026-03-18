<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Authentication\Authenticator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\Authenticator\FormLoginAuthenticator;
use WpPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;
use WpPack\Component\Security\Authentication\Passport\Badge\RememberMeBadge;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Exception\AuthenticationException;

final class FormLoginAuthenticatorTest extends TestCase
{
    private FormLoginAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->authenticator = new FormLoginAuthenticator();
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
