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

namespace WpPack\Plugin\OAuthLoginPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration;
use WpPack\Plugin\OAuthLoginPlugin\Configuration\ProviderConfiguration;
use WpPack\Plugin\OAuthLoginPlugin\OAuthLoginForm;

#[CoversClass(OAuthLoginForm::class)]
final class OAuthLoginFormTest extends TestCase
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

    private function createProviders(): array
    {
        return [
            new ProviderConfiguration(
                name: 'google',
                type: 'google',
                clientId: 'google-id',
                clientSecret: 'google-secret',
                label: 'Google',
            ),
            new ProviderConfiguration(
                name: 'github',
                type: 'github',
                clientId: 'github-id',
                clientSecret: 'github-secret',
                label: 'GitHub',
            ),
        ];
    }

    private function createConfig(): OAuthLoginConfiguration
    {
        $google = new ProviderConfiguration(
            name: 'google',
            type: 'google',
            clientId: 'google-id',
            clientSecret: 'google-secret',
            label: 'Google',
        );

        $github = new ProviderConfiguration(
            name: 'github',
            type: 'github',
            clientId: 'github-id',
            clientSecret: 'github-secret',
            label: 'GitHub',
        );

        return new OAuthLoginConfiguration(
            providers: ['google' => $google, 'github' => $github],
        );
    }

    #[Test]
    public function registerAddsHooks(): void
    {
        $providers = $this->createProviders();
        $config = $this->createConfig();
        $request = Request::create('https://example.com/wp-login.php');

        $form = new OAuthLoginForm($providers, $config, $this->authSession, $request);
        $form->register();

        self::assertSame(10, has_action('login_init', [$form, 'redirectLoggedInUser']));
        self::assertSame(10, has_action('login_footer', [$form, 'renderButtons']));
        self::assertSame(10, has_filter('wp_login_errors', [$form, 'addOAuthError']));
    }

    #[Test]
    public function renderButtonsOutputsProviderButtons(): void
    {
        $providers = $this->createProviders();
        $config = $this->createConfig();
        $request = Request::create('https://example.com/wp-login.php');

        $form = new OAuthLoginForm($providers, $config, $this->authSession, $request);

        ob_start();
        $form->renderButtons();
        $output = ob_get_clean();

        self::assertStringContainsString('wppack-oauth-login', $output);
        self::assertStringContainsString('Google でログイン', $output);
        self::assertStringContainsString('GitHub でログイン', $output);
        self::assertStringContainsString('/oauth/google/authorize', $output);
        self::assertStringContainsString('/oauth/github/authorize', $output);
    }

    #[Test]
    public function renderButtonsOutputsNothingWhenProvidersEmpty(): void
    {
        $config = new OAuthLoginConfiguration(providers: []);
        $request = Request::create('https://example.com/wp-login.php');

        $form = new OAuthLoginForm([], $config, $this->authSession, $request);

        ob_start();
        $form->renderButtons();
        $output = ob_get_clean();

        self::assertSame('', $output);
    }

    #[Test]
    public function addOAuthErrorAddsErrorWhenOauthErrorPresent(): void
    {
        $providers = $this->createProviders();
        $config = $this->createConfig();
        $request = Request::create('https://example.com/wp-login.php?oauth_error=1');

        $form = new OAuthLoginForm($providers, $config, $this->authSession, $request);
        $errors = $form->addOAuthError(new \WP_Error());

        self::assertSame('OAuth authentication failed. Please try again.', $errors->get_error_message('oauth_error'));
    }

    #[Test]
    public function addOAuthErrorPassesThroughWhenNoError(): void
    {
        $providers = $this->createProviders();
        $config = $this->createConfig();
        $request = Request::create('https://example.com/wp-login.php');

        $form = new OAuthLoginForm($providers, $config, $this->authSession, $request);
        $errors = $form->addOAuthError(new \WP_Error());

        self::assertEmpty($errors->get_error_codes());
    }

    #[Test]
    public function addOAuthErrorPreservesExistingErrors(): void
    {
        $providers = $this->createProviders();
        $config = $this->createConfig();
        $request = Request::create('https://example.com/wp-login.php?oauth_error=1');

        $form = new OAuthLoginForm($providers, $config, $this->authSession, $request);
        $existing = new \WP_Error('invalid_username', 'Unknown username.');
        $errors = $form->addOAuthError($existing);

        self::assertSame('Unknown username.', $errors->get_error_message('invalid_username'));
        self::assertSame('OAuth authentication failed. Please try again.', $errors->get_error_message('oauth_error'));
    }

    #[Test]
    public function renderButtonsIncludesReturnToParam(): void
    {
        $providers = $this->createProviders();
        $config = $this->createConfig();
        $redirectTo = 'https://example.com/wp-admin/edit.php';
        $request = Request::create('https://example.com/wp-login.php?redirect_to=' . urlencode($redirectTo));

        $form = new OAuthLoginForm($providers, $config, $this->authSession, $request);

        ob_start();
        $form->renderButtons();
        $output = ob_get_clean();

        self::assertStringContainsString('return_to=', $output);
    }

    #[Test]
    public function redirectLoggedInUserRedirectsToAdminUrl(): void
    {
        $capturedLocation = null;

        add_filter('wp_redirect', function (string $location) use (&$capturedLocation): string {
            $capturedLocation = $location;
            throw new \RuntimeException('redirect intercepted');
        });

        $providers = $this->createProviders();
        $config = $this->createConfig();
        $request = Request::create('https://example.com/wp-login.php');

        wp_set_current_user(1);

        $form = new OAuthLoginForm($providers, $config, $this->authSession, $request);

        try {
            $form->redirectLoggedInUser();
        } catch (\Throwable) {
            // wp_safe_redirect throws to prevent exit
        }

        self::assertSame(admin_url(), $capturedLocation);
    }

    #[Test]
    public function redirectLoggedInUserSkipsAnonymousUser(): void
    {
        $redirectCalled = false;

        add_filter('wp_redirect', function (string $location) use (&$redirectCalled): string {
            $redirectCalled = true;

            return $location;
        });

        $providers = $this->createProviders();
        $config = $this->createConfig();
        $request = Request::create('https://example.com/wp-login.php');

        $form = new OAuthLoginForm($providers, $config, $this->authSession, $request);
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

        $providers = $this->createProviders();
        $config = $this->createConfig();
        $request = Request::create('https://example.com/wp-login.php', 'POST');

        wp_set_current_user(1);

        $form = new OAuthLoginForm($providers, $config, $this->authSession, $request);
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

        $providers = $this->createProviders();
        $config = $this->createConfig();
        $request = Request::create('https://example.com/wp-login.php?action=logout');

        wp_set_current_user(1);

        $form = new OAuthLoginForm($providers, $config, $this->authSession, $request);
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

        $providers = $this->createProviders();
        $config = $this->createConfig();
        $request = Request::create('https://example.com/wp-login.php?loggedout=true');

        wp_set_current_user(1);

        $form = new OAuthLoginForm($providers, $config, $this->authSession, $request);
        $form->redirectLoggedInUser();

        self::assertFalse($redirectCalled);
    }
}
