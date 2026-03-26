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

namespace WpPack\Plugin\SamlLoginPlugin\Tests;

use OneLogin\Saml2\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\SamlEntryPoint;
use WpPack\Plugin\SamlLoginPlugin\SamlLoginForm;

#[CoversClass(SamlLoginForm::class)]
final class SamlLoginFormTest extends TestCase
{
    protected function tearDown(): void
    {
        remove_all_actions('login_footer');
        remove_all_filters('login_message');
    }

    private function createEntryPoint(string $loginUrl = 'https://idp.example.com/sso'): SamlEntryPoint
    {
        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->willReturn($loginUrl);

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        return new SamlEntryPoint(
            $factory,
            new AuthenticationSession(),
            Request::create('https://example.com/wp-login.php'),
        );
    }

    #[Test]
    public function registerAddsHooks(): void
    {
        $entryPoint = $this->createEntryPoint();
        $request = Request::create('https://example.com/wp-login.php');

        $form = new SamlLoginForm($entryPoint, $request);
        $form->register();

        self::assertSame(10, has_action('login_footer', [$form, 'renderButton']));
        self::assertSame(10, has_filter('login_message', [$form, 'renderErrorMessage']));
    }

    #[Test]
    public function renderButtonOutputsSsoButton(): void
    {
        $entryPoint = $this->createEntryPoint('https://idp.example.com/sso?SAMLRequest=encoded');
        $request = Request::create('https://example.com/wp-login.php');

        $form = new SamlLoginForm($entryPoint, $request);

        ob_start();
        $form->renderButton();
        $output = ob_get_clean();

        self::assertStringContainsString('wppack-saml-login', $output);
        self::assertStringContainsString('Login with SSO', $output);
        self::assertStringContainsString('https://idp.example.com/sso?SAMLRequest=encoded', $output);
        self::assertStringContainsString('button button-large', $output);
    }

    #[Test]
    public function renderButtonPassesRedirectToAsReturnTo(): void
    {
        $capturedReturnTo = null;

        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->willReturnCallback(function (?string $returnTo) use (&$capturedReturnTo): string {
                $capturedReturnTo = $returnTo;

                return 'https://idp.example.com/sso';
            });

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint(
            $factory,
            new AuthenticationSession(),
            Request::create('https://example.com/wp-login.php'),
        );

        $request = Request::create('https://example.com/wp-login.php?redirect_to=' . urlencode('https://example.com/wp-admin/edit.php'));

        $form = new SamlLoginForm($entryPoint, $request);

        ob_start();
        $form->renderButton();
        ob_end_clean();

        self::assertSame('https://example.com/wp-admin/edit.php', $capturedReturnTo);
    }

    #[Test]
    public function renderButtonDefaultsToAdminUrlWhenNoRedirectTo(): void
    {
        $capturedReturnTo = 'not-called';

        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->willReturnCallback(function (?string $returnTo) use (&$capturedReturnTo): string {
                $capturedReturnTo = $returnTo;

                return 'https://idp.example.com/sso';
            });

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint(
            $factory,
            new AuthenticationSession(),
            Request::create('https://example.com/wp-login.php'),
        );

        $request = Request::create('https://example.com/wp-login.php');

        $form = new SamlLoginForm($entryPoint, $request);

        ob_start();
        $form->renderButton();
        ob_end_clean();

        self::assertSame(admin_url(), $capturedReturnTo);
    }

    #[Test]
    public function renderErrorMessageShowsErrorOnSamlError(): void
    {
        $entryPoint = $this->createEntryPoint();
        $request = Request::create('https://example.com/wp-login.php?action=saml_error');

        $form = new SamlLoginForm($entryPoint, $request);
        $result = $form->renderErrorMessage('');

        self::assertStringContainsString('login_error', $result);
        self::assertStringContainsString('SAML authentication failed', $result);
    }

    #[Test]
    public function renderErrorMessageReturnsOriginalWithoutSamlError(): void
    {
        $entryPoint = $this->createEntryPoint();
        $request = Request::create('https://example.com/wp-login.php');

        $form = new SamlLoginForm($entryPoint, $request);
        $result = $form->renderErrorMessage('existing message');

        self::assertSame('existing message', $result);
    }

    #[Test]
    public function renderErrorMessagePrependsToExistingMessage(): void
    {
        $entryPoint = $this->createEntryPoint();
        $request = Request::create('https://example.com/wp-login.php?action=saml_error');

        $form = new SamlLoginForm($entryPoint, $request);
        $result = $form->renderErrorMessage('<p>Welcome</p>');

        self::assertStringContainsString('SAML authentication failed', $result);
        self::assertStringContainsString('<p>Welcome</p>', $result);
    }
}
