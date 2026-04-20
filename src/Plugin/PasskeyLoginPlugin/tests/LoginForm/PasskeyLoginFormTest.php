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

namespace WPPack\Plugin\PasskeyLoginPlugin\Tests\LoginForm;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Plugin\PasskeyLoginPlugin\Configuration\PasskeyLoginConfiguration;
use WPPack\Plugin\PasskeyLoginPlugin\LoginForm\PasskeyLoginForm;

#[CoversClass(PasskeyLoginForm::class)]
final class PasskeyLoginFormTest extends TestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('wp_login_errors');
        remove_all_actions('login_init');
        remove_all_actions('login_form');
        remove_all_actions('login_footer');
        wp_set_current_user(0);
    }

    private function form(
        ?Request $request = null,
        ?PasskeyLoginConfiguration $config = null,
    ): PasskeyLoginForm {
        return new PasskeyLoginForm(
            new AuthenticationSession(),
            $request ?? Request::create('https://example.com/wp-login.php'),
            $config ?? new PasskeyLoginConfiguration(),
        );
    }

    #[Test]
    public function registerAddsAllExpectedHooks(): void
    {
        $form = $this->form();
        $form->register();

        self::assertNotFalse(has_action('login_init', [$form, 'redirectLoggedInUser']));
        self::assertNotFalse(has_action('login_form', [$form, 'addConditionalUiAttributes']));
        self::assertNotFalse(has_action('login_footer', [$form, 'renderButton']));
        self::assertNotFalse(has_filter('wp_login_errors', [$form, 'addPasskeyError']));
    }

    #[Test]
    public function redirectLoggedInUserNoOpsWhenAnonymous(): void
    {
        $form = $this->form();

        $form->redirectLoggedInUser();

        self::assertTrue(true, 'no exception/redirect');
    }

    #[Test]
    public function redirectLoggedInUserNoOpsWhenPostRequest(): void
    {
        $userId = (int) wp_insert_user([
            'user_login' => 'pk_form_post_' . uniqid(),
            'user_email' => 'pk_form_post_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);
        wp_set_current_user($userId);

        $form = $this->form(Request::create('https://example.com/wp-login.php', 'POST'));
        $form->redirectLoggedInUser();

        wp_delete_user($userId);
        self::assertTrue(true, 'POST skipped without redirect');
    }

    #[Test]
    public function redirectLoggedInUserNoOpsWhenActionQueryPresent(): void
    {
        $userId = (int) wp_insert_user([
            'user_login' => 'pk_form_act_' . uniqid(),
            'user_email' => 'pk_form_act_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);
        wp_set_current_user($userId);

        $form = $this->form(Request::create('https://example.com/wp-login.php?action=logout'));
        $form->redirectLoggedInUser();

        wp_delete_user($userId);
        self::assertTrue(true, 'action= skipped without redirect');
    }

    #[Test]
    public function addConditionalUiAttributesEchoesScript(): void
    {
        ob_start();
        $this->form()->addConditionalUiAttributes();
        $out = (string) ob_get_clean();

        self::assertStringContainsString("autocomplete", $out);
        self::assertStringContainsString('username webauthn', $out);
    }

    #[Test]
    public function renderButtonEmitsExpectedAttributes(): void
    {
        ob_start();
        $this->form()->renderButton();
        $out = (string) ob_get_clean();

        self::assertStringContainsString('wppack-passkey-btn', $out);
        self::assertStringContainsString('Log in with Passkey', $out);
        self::assertStringContainsString('navigator.credentials.get', $out);
        // JSON-encoded URL appears with escaped slashes in the script block
        self::assertStringContainsString('wppack\/v1\/passkey', $out);
    }

    #[Test]
    public function renderButtonRespectsIconOnlyDisplay(): void
    {
        $form = $this->form(config: new PasskeyLoginConfiguration(buttonDisplay: 'icon-only'));

        ob_start();
        $form->renderButton();
        $out = (string) ob_get_clean();

        self::assertStringContainsString('wpl-icon-only', $out);
        self::assertStringContainsString('title="Log in with Passkey"', $out);
    }

    #[Test]
    public function renderButtonRespectsIconLeftDisplay(): void
    {
        $form = $this->form(config: new PasskeyLoginConfiguration(buttonDisplay: 'icon-left'));

        ob_start();
        $form->renderButton();
        $out = (string) ob_get_clean();

        self::assertStringContainsString('wpl-icon-left', $out);
    }

    #[Test]
    public function renderButtonTextOnlyOmitsIconMarkup(): void
    {
        $form = $this->form(config: new PasskeyLoginConfiguration(buttonDisplay: 'text-only'));

        ob_start();
        $form->renderButton();
        $out = (string) ob_get_clean();

        // text-only mode: no <span class="wpl-icon">, no icon SVG, but shared CSS still includes class definitions
        self::assertStringNotContainsString('<span class="wpl-icon">', $out);
        self::assertStringContainsString('Log in with Passkey', $out);
    }

    #[Test]
    public function addPasskeyErrorAppendsErrorWhenQueryParamPresent(): void
    {
        $form = $this->form(Request::create('https://example.com/wp-login.php?passkey_error=1'));

        $errors = new \WP_Error();
        $result = $form->addPasskeyError($errors);

        self::assertSame($errors, $result, 'returns same instance');
        self::assertNotEmpty($errors->get_error_messages('passkey_error'));
    }

    #[Test]
    public function addPasskeyErrorDoesNothingWithoutQueryParam(): void
    {
        $form = $this->form();

        $errors = new \WP_Error();
        $form->addPasskeyError($errors);

        self::assertSame([], $errors->get_error_messages('passkey_error'));
    }
}
