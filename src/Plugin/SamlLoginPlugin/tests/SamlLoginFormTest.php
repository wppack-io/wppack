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
    private AuthenticationSession $authSession;

    protected function setUp(): void
    {
        $this->authSession = new AuthenticationSession();
        wp_set_current_user(0);
    }

    protected function tearDown(): void
    {
        remove_all_actions('login_init');
        remove_all_actions('login_footer');
        remove_all_filters('wp_login_errors');
        remove_all_filters('wp_redirect');
        wp_set_current_user(0);
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
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
        );
    }

    #[Test]
    public function registerAddsHooks(): void
    {
        $entryPoint = $this->createEntryPoint();
        $request = Request::create('https://example.com/wp-login.php');

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);
        $form->register();

        self::assertSame(10, has_action('login_init', [$form, 'redirectLoggedInUser']));
        self::assertSame(10, has_action('login_footer', [$form, 'renderButton']));
        self::assertSame(10, has_filter('wp_login_errors', [$form, 'addSamlError']));
    }

    #[Test]
    public function renderButtonOutputsSsoButton(): void
    {
        $entryPoint = $this->createEntryPoint('https://idp.example.com/sso?SAMLRequest=encoded');
        $request = Request::create('https://example.com/wp-login.php');

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);

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
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
        );

        $request = Request::create('https://example.com/wp-login.php?redirect_to=' . urlencode('https://example.com/wp-admin/edit.php'));

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);

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
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
        );

        $request = Request::create('https://example.com/wp-login.php');

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);

        ob_start();
        $form->renderButton();
        ob_end_clean();

        self::assertSame(admin_url(), $capturedReturnTo);
    }

    #[Test]
    public function redirectLoggedInUserRedirectsToAdminUrl(): void
    {
        $capturedLocation = null;

        add_filter('wp_redirect', function (string $location) use (&$capturedLocation): string {
            $capturedLocation = $location;
            throw new \RuntimeException('redirect intercepted');
        });

        $entryPoint = $this->createEntryPoint();
        $request = Request::create('https://example.com/wp-login.php');

        wp_set_current_user(1);

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);

        try {
            $form->redirectLoggedInUser();
        } catch (\Throwable) {
            // wp_safe_redirect throws to prevent exit
        }

        self::assertSame(admin_url(), $capturedLocation);
    }

    #[Test]
    public function redirectLoggedInUserRedirectsToRedirectTo(): void
    {
        $capturedLocation = null;

        add_filter('wp_redirect', function (string $location) use (&$capturedLocation): string {
            $capturedLocation = $location;
            throw new \RuntimeException('redirect intercepted');
        });

        $entryPoint = $this->createEntryPoint();
        $redirectTo = home_url('/wp-admin/edit.php');
        $request = Request::create('https://example.com/wp-login.php?redirect_to=' . urlencode($redirectTo));

        wp_set_current_user(1);

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);

        try {
            $form->redirectLoggedInUser();
        } catch (\Throwable) {
            // wp_safe_redirect throws to prevent exit
        }

        self::assertSame($redirectTo, $capturedLocation);
    }

    #[Test]
    public function redirectLoggedInUserSkipsAnonymousUser(): void
    {
        $redirectCalled = false;

        add_filter('wp_redirect', function (string $location) use (&$redirectCalled): string {
            $redirectCalled = true;

            return $location;
        });

        $entryPoint = $this->createEntryPoint();
        $request = Request::create('https://example.com/wp-login.php');

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);
        $form->redirectLoggedInUser();

        self::assertFalse($redirectCalled);
    }

    #[Test]
    public function redirectLoggedInUserSkipsPostRequest(): void
    {
        $redirectCalled = false;

        add_filter('wp_redirect', function (string $location) use (&$redirectCalled): string {
            $redirectCalled = true;

            return $location;
        });

        $entryPoint = $this->createEntryPoint();
        $request = Request::create('https://example.com/wp-login.php', 'POST');

        wp_set_current_user(1);

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);
        $form->redirectLoggedInUser();

        self::assertFalse($redirectCalled);
    }

    #[Test]
    public function redirectLoggedInUserSkipsWithAction(): void
    {
        $redirectCalled = false;

        add_filter('wp_redirect', function (string $location) use (&$redirectCalled): string {
            $redirectCalled = true;

            return $location;
        });

        $entryPoint = $this->createEntryPoint();
        $request = Request::create('https://example.com/wp-login.php?action=logout');

        wp_set_current_user(1);

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);
        $form->redirectLoggedInUser();

        self::assertFalse($redirectCalled);
    }

    #[Test]
    public function redirectLoggedInUserSkipsWithLoggedout(): void
    {
        $redirectCalled = false;

        add_filter('wp_redirect', function (string $location) use (&$redirectCalled): string {
            $redirectCalled = true;

            return $location;
        });

        $entryPoint = $this->createEntryPoint();
        $request = Request::create('https://example.com/wp-login.php?loggedout=true');

        wp_set_current_user(1);

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);
        $form->redirectLoggedInUser();

        self::assertFalse($redirectCalled);
    }

    #[Test]
    public function addSamlErrorAddsErrorOnSamlError(): void
    {
        $entryPoint = $this->createEntryPoint();
        $request = Request::create('https://example.com/wp-login.php?saml_error=true');

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);
        $errors = $form->addSamlError(new \WP_Error());

        self::assertSame('SAML authentication failed. Please try again.', $errors->get_error_message('saml_error'));
    }

    #[Test]
    public function addSamlErrorDoesNothingWithoutSamlError(): void
    {
        $entryPoint = $this->createEntryPoint();
        $request = Request::create('https://example.com/wp-login.php');

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);
        $errors = $form->addSamlError(new \WP_Error());

        self::assertEmpty($errors->get_error_codes());
    }

    #[Test]
    public function addSamlErrorPreservesExistingErrors(): void
    {
        $entryPoint = $this->createEntryPoint();
        $request = Request::create('https://example.com/wp-login.php?saml_error=true');

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);
        $existing = new \WP_Error('invalid_username', 'Unknown username.');
        $errors = $form->addSamlError($existing);

        self::assertSame('Unknown username.', $errors->get_error_message('invalid_username'));
        self::assertSame('SAML authentication failed. Please try again.', $errors->get_error_message('saml_error'));
    }
}
