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
use WPPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WPPack\Component\Security\Bridge\OAuth\OAuthCallbackController;

#[CoversClass(OAuthCallbackController::class)]
final class OAuthCallbackControllerTest extends TestCase
{
    #[Test]
    public function redirectsToAdminWhenAuthenticationProducesWpUser(): void
    {
        $user = new \WP_User();
        $manager = $this->createMock(AuthenticationManagerInterface::class);
        $manager->method('handleAuthentication')->willReturn($user);

        $response = (new OAuthCallbackController($manager))();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(admin_url(), $response->url);
    }

    #[Test]
    public function redirectsToAdminWhenAuthenticationReturnsWpError(): void
    {
        $manager = $this->createMock(AuthenticationManagerInterface::class);
        $manager->method('handleAuthentication')->willReturn(new \WP_Error('oauth_failed', 'x'));

        $response = (new OAuthCallbackController($manager))();

        self::assertSame(admin_url(), $response->url);
    }

    #[Test]
    public function redirectsToLoginWithErrorQueryWhenAuthenticationReturnsNothing(): void
    {
        $manager = $this->createMock(AuthenticationManagerInterface::class);
        $manager->method('handleAuthentication')->willReturn(null);

        $response = (new OAuthCallbackController($manager))();

        self::assertStringContainsString(wp_login_url(), $response->url);
        self::assertStringContainsString('oauth_error=1', $response->url);
    }

    #[Test]
    public function invokesAuthenticationManagerExactlyOnce(): void
    {
        $manager = $this->createMock(AuthenticationManagerInterface::class);
        $manager->expects(self::once())
            ->method('handleAuthentication')
            ->with(null, '', '')
            ->willReturn(null);

        (new OAuthCallbackController($manager))();
    }
}
