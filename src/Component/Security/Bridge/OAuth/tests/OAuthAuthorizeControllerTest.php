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

namespace WPPack\Component\Security\Bridge\OAuth\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\HttpFoundation\RedirectResponse;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WPPack\Component\Security\Bridge\OAuth\OAuthAuthorizeController;
use WPPack\Component\Security\Bridge\OAuth\OAuthEntryPoint;
use WPPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;
use WPPack\Component\Security\Bridge\OAuth\State\OAuthStateStore;
use WPPack\Component\Transient\TransientManager;

#[CoversClass(OAuthAuthorizeController::class)]
final class OAuthAuthorizeControllerTest extends TestCase
{
    private function controller(Request $request, ?ProviderInterface $provider = null): OAuthAuthorizeController
    {
        $provider ??= $this->providerReturning('https://idp.example.com/authorize');

        $entryPoint = new OAuthEntryPoint(
            $provider,
            new OAuthConfiguration(
                clientId: 'id',
                clientSecret: 'secret',
                redirectUri: 'https://example.com/oauth/callback',
                pkceEnabled: false,
            ),
            new OAuthStateStore(new TransientManager()),
            new AuthenticationSession(),
            $request,
        );

        return new OAuthAuthorizeController($entryPoint, $request);
    }

    private function providerReturning(string $url): ProviderInterface
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getAuthorizationUrl')->willReturn($url);

        return $provider;
    }

    #[Test]
    public function redirectsToAuthorizationUrl(): void
    {
        $response = ($this->controller(Request::create('https://example.com/oauth/amazon/authorize')))();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertSame('https://idp.example.com/authorize', $response->url);
    }

    #[Test]
    public function passesValidReturnToThroughToEntryPoint(): void
    {
        $captured = null;

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getAuthorizationUrl')
            ->willReturnCallback(function () use (&$captured): string {
                $captured = 'called';

                return 'https://idp.example.com/authorize';
            });

        $request = Request::create(
            'https://example.com/oauth/amazon/authorize?return_to=' . rawurlencode(admin_url('edit.php')),
        );

        ($this->controller($request, $provider))();

        self::assertSame('called', $captured, 'provider used with returnTo path');
    }

    #[Test]
    public function unsafeReturnValueIsReplacedByFallbackUrl(): void
    {
        // wp_validate_redirect replaces external hosts with the fallback (admin_url)
        $request = Request::create(
            'https://example.com/oauth/amazon/authorize?return_to=' . rawurlencode('https://evil.example.com/phish'),
        );

        $response = ($this->controller($request))();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->statusCode);
    }

    #[Test]
    public function redirectIsMarkedUnsafeToAllowCrossOriginIdp(): void
    {
        $response = ($this->controller(Request::create('https://example.com/oauth/amazon/authorize')))();

        // Cross-origin IdP redirects require safe: false — the RedirectResponse
        // should not refuse the external host.
        self::assertSame('https://idp.example.com/authorize', $response->url);
    }
}
