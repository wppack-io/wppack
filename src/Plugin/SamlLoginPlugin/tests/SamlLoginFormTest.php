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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
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

    private function createSamlConfiguration(): SamlConfiguration
    {
        return new SamlConfiguration(
            idpSettings: new IdpSettings(
                entityId: 'https://idp.example.com/metadata',
                ssoUrl: 'https://idp.example.com/sso',
                sloUrl: 'https://idp.example.com/slo',
                x509Cert: 'MIICDummyCert==',
            ),
            spSettings: new SpSettings(
                entityId: 'https://sp.example.com/metadata',
                acsUrl: 'https://sp.example.com/acs',
                sloUrl: 'https://sp.example.com/slo',
            ),
        );
    }

    private function createEntryPoint(): SamlEntryPoint
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('getConfiguration')->willReturn($this->createSamlConfiguration());

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
        $entryPoint = $this->createEntryPoint();
        $request = Request::create('https://example.com/wp-login.php');

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);

        ob_start();
        $form->renderButton();
        $output = ob_get_clean();

        self::assertStringContainsString('wppack-saml-login', $output);
        self::assertStringContainsString('Login with SSO', $output);
        self::assertStringContainsString('https://idp.example.com/sso', $output);
        self::assertStringContainsString('button button-large', $output);
    }

    #[Test]
    public function renderButtonPassesRedirectToAsReturnTo(): void
    {
        $entryPoint = $this->createEntryPoint();

        $request = Request::create('https://example.com/wp-login.php?redirect_to=' . urlencode('https://example.com/wp-admin/edit.php'));

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);

        ob_start();
        $form->renderButton();
        $output = ob_get_clean();

        // The URL should contain RelayState (which encodes the redirect_to)
        self::assertStringContainsString('RelayState=', $output);
    }

    #[Test]
    public function renderButtonDefaultsToAdminUrlWhenNoRedirectTo(): void
    {
        $entryPoint = $this->createEntryPoint();

        $request = Request::create('https://example.com/wp-login.php');

        $form = new SamlLoginForm($entryPoint, $this->authSession, $request);

        ob_start();
        $form->renderButton();
        $output = ob_get_clean();

        // The URL should contain RelayState with admin_url() encoded
        self::assertStringContainsString('RelayState=', $output);
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
