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
use WPPack\Component\Security\Bridge\OAuth\OAuthVerifyController;

#[CoversClass(OAuthVerifyController::class)]
final class OAuthVerifyControllerTest extends TestCase
{
    #[Test]
    public function redirectsToAdminOnSuccessfulVerification(): void
    {
        $manager = $this->createMock(AuthenticationManagerInterface::class);
        $manager->method('handleAuthentication')->willReturn(new \WP_User());

        $response = (new OAuthVerifyController($manager))();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(admin_url(), $response->url);
    }

    #[Test]
    public function redirectsToAdminEvenForWpError(): void
    {
        $manager = $this->createMock(AuthenticationManagerInterface::class);
        $manager->method('handleAuthentication')->willReturn(new \WP_Error('x', 'y'));

        $response = (new OAuthVerifyController($manager))();

        self::assertSame(admin_url(), $response->url);
    }

    #[Test]
    public function redirectsToLoginWithErrorWhenTokenMissing(): void
    {
        $manager = $this->createMock(AuthenticationManagerInterface::class);
        $manager->method('handleAuthentication')->willReturn(null);

        $response = (new OAuthVerifyController($manager))();

        self::assertStringContainsString('oauth_error=1', $response->url);
    }
}
