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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\Bridge\SAML\SamlAcsController;

#[CoversClass(SamlAcsController::class)]
final class SamlAcsControllerTest extends TestCase
{
    #[Test]
    public function invokeReturnsEmptyResponseOnSuccess(): void
    {
        $user = new \WP_User();
        $user->ID = 1;

        $authManager = $this->createMock(AuthenticationManagerInterface::class);
        $authManager->method('handleAuthentication')
            ->with(null, '', '')
            ->willReturn($user);

        $controller = new SamlAcsController($authManager);
        $response = $controller();

        self::assertNotInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertSame('', $response->content);
    }

    #[Test]
    public function invokeReturnsEmptyResponseOnFailure(): void
    {
        $authManager = $this->createMock(AuthenticationManagerInterface::class);
        $authManager->method('handleAuthentication')
            ->with(null, '', '')
            ->willReturn(new \WP_Error('authentication_failed', 'SAML error'));

        $controller = new SamlAcsController($authManager);
        $response = $controller();

        self::assertNotInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertSame('', $response->content);
    }

    #[Test]
    public function invokeRedirectsToLoginOnNull(): void
    {
        $authManager = $this->createMock(AuthenticationManagerInterface::class);
        $authManager->method('handleAuthentication')
            ->with(null, '', '')
            ->willReturn(null);

        $controller = new SamlAcsController($authManager);
        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(wp_login_url() . '?action=saml_error', $response->url);
    }
}
