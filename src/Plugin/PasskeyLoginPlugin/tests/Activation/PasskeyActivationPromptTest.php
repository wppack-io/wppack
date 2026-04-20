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

namespace WPPack\Plugin\PasskeyLoginPlugin\Tests\Activation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Transient\TransientManager;
use WPPack\Plugin\PasskeyLoginPlugin\Activation\PasskeyActivationPrompt;

#[CoversClass(PasskeyActivationPrompt::class)]
final class PasskeyActivationPromptTest extends TestCase
{
    private TransientManager $transients;

    protected function setUp(): void
    {
        $this->transients = new TransientManager();
    }

    #[Test]
    public function validateTokenReturnsUserIdWhenStored(): void
    {
        $prompt = new PasskeyActivationPrompt($this->transients);
        $prompt->onUserActivated(42, 'pw', []);

        $token = $this->extractToken($prompt);

        self::assertSame(42, $prompt->validateToken($token));
    }

    #[Test]
    public function validateTokenReturnsNullForUnknownToken(): void
    {
        $prompt = new PasskeyActivationPrompt($this->transients);

        self::assertNull($prompt->validateToken('nonexistent-token'));
    }

    #[Test]
    public function validateTokenDoesNotConsume(): void
    {
        $prompt = new PasskeyActivationPrompt($this->transients);
        $prompt->onUserActivated(42, 'pw', []);
        $token = $this->extractToken($prompt);

        self::assertSame(42, $prompt->validateToken($token));
        self::assertSame(42, $prompt->validateToken($token), 'token is still valid after validation');
    }

    #[Test]
    public function consumeTokenReturnsUserIdAndDeletesIt(): void
    {
        $prompt = new PasskeyActivationPrompt($this->transients);
        $prompt->onUserActivated(42, 'pw', []);
        $token = $this->extractToken($prompt);

        self::assertSame(42, $prompt->consumeToken($token));
        self::assertNull($prompt->consumeToken($token), 'token is one-time use');
        self::assertNull($prompt->validateToken($token));
    }

    #[Test]
    public function consumeTokenReturnsNullForUnknownToken(): void
    {
        $prompt = new PasskeyActivationPrompt($this->transients);

        self::assertNull($prompt->consumeToken('nope'));
    }

    #[Test]
    public function validateTokenReturnsNullForNonIntegerTransient(): void
    {
        // Manually poison the transient with a non-int value.
        $token = 'manual-token-' . uniqid();
        set_transient('wppack_passkey_activate_' . $token, 'string-value', 300);

        $prompt = new PasskeyActivationPrompt($this->transients);

        self::assertNull($prompt->validateToken($token));

        delete_transient('wppack_passkey_activate_' . $token);
    }

    #[Test]
    public function onUserActivatedStoresFreshTokenPerCall(): void
    {
        $prompt = new PasskeyActivationPrompt($this->transients);
        $prompt->onUserActivated(1, 'pw', []);
        $first = $this->extractToken($prompt);

        $prompt->onUserActivated(2, 'pw', []);
        $second = $this->extractToken($prompt);

        self::assertNotSame($first, $second, 'each activation gets a new token');
        // Old token still resolves to user 1, new to user 2 (state is kept per-token)
        self::assertSame(1, $prompt->validateToken($first));
        self::assertSame(2, $prompt->validateToken($second));
    }

    #[Test]
    public function renderPromptEmitsNothingWithoutActivation(): void
    {
        $prompt = new PasskeyActivationPrompt($this->transients);

        ob_start();
        $prompt->renderPrompt();
        $out = (string) ob_get_clean();

        self::assertSame('', $out);
    }

    #[Test]
    public function renderPromptEmitsHtmlAfterActivation(): void
    {
        $prompt = new PasskeyActivationPrompt($this->transients);
        $prompt->onUserActivated(42, 'pw', []);

        ob_start();
        $prompt->renderPrompt();
        $out = (string) ob_get_clean();

        self::assertStringContainsString('wppack-passkey-activate', $out);
        self::assertStringContainsString('navigator.credentials.create', $out);
        self::assertStringContainsString($this->extractToken($prompt), $out);
    }

    #[Test]
    public function registerWiresExpectedHooks(): void
    {
        $prompt = new PasskeyActivationPrompt($this->transients);
        $prompt->register();

        self::assertNotFalse(has_action('wpmu_activate_user', [$prompt, 'onUserActivated']));
        self::assertNotFalse(has_action('wp_footer', [$prompt, 'renderPrompt']));

        remove_action('wpmu_activate_user', [$prompt, 'onUserActivated'], 10);
        remove_action('wp_footer', [$prompt, 'renderPrompt'], 10);
    }

    private function extractToken(PasskeyActivationPrompt $prompt): string
    {
        $r = new \ReflectionProperty($prompt, 'activationToken');

        return (string) $r->getValue($prompt);
    }
}
