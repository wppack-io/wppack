<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Authentication\Authenticator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\Authenticator\ApplicationPasswordAuthenticator;

final class ApplicationPasswordAuthenticatorTest extends TestCase
{
    private ApplicationPasswordAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->authenticator = new ApplicationPasswordAuthenticator();
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
}
