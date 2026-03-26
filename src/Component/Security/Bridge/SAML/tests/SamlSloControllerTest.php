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
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\SamlLogoutHandler;
use WpPack\Component\Security\Bridge\SAML\SamlSloController;

#[CoversClass(SamlSloController::class)]
final class SamlSloControllerTest extends TestCase
{
    private SamlLogoutHandler $logoutHandler;

    /** @var array<string, string> */
    private array $originalGet;

    protected function setUp(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->method('processSLO');

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $this->logoutHandler = new SamlLogoutHandler($factory);

        $this->originalGet = $_GET;
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
    }

    #[Test]
    public function invokeHandlesLogoutRequest(): void
    {
        $_GET = ['SAMLRequest' => 'encoded-request'];

        $request = new Request(
            query: ['SAMLRequest' => 'encoded-request'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $request);
        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(home_url(), $response->url);
    }

    #[Test]
    public function invokeHandlesLogoutResponse(): void
    {
        $_GET = ['SAMLResponse' => 'encoded-response'];

        $request = new Request(
            query: ['SAMLResponse' => 'encoded-response'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $request);
        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(home_url(), $response->url);
    }

    #[Test]
    public function invokeReturnsBadRequestForUnknownRequest(): void
    {
        $_GET = [];

        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $controller = new SamlSloController($this->logoutHandler, $request);
        $response = $controller();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->statusCode);
    }
}
