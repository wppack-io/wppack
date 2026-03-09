<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Authentication\Authenticator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\Authenticator\FormLoginAuthenticator;
use WpPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;

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
}
