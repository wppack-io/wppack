<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Authentication\Authenticator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\Authenticator\CookieAuthenticator;

final class CookieAuthenticatorTest extends TestCase
{
    private CookieAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->authenticator = new CookieAuthenticator();
    }

    #[Test]
    public function supportsRequestWithAuthCookie(): void
    {
        $cookieName = LOGGED_IN_COOKIE;

        $request = new Request(
            cookies: [$cookieName => 'some_cookie_value'],
            server: ['REQUEST_METHOD' => 'GET'],
        );

        self::assertTrue($this->authenticator->supports($request));
    }

    #[Test]
    public function doesNotSupportRequestWithoutCookie(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET'],
        );

        self::assertFalse($this->authenticator->supports($request));
    }
}
