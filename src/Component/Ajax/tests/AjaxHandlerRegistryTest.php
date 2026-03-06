<?php

declare(strict_types=1);

namespace WpPack\Component\Ajax\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Ajax\Access;
use WpPack\Component\Ajax\AjaxHandlerRegistry;
use WpPack\Component\Ajax\Attribute\AjaxHandler;
use WpPack\Component\HttpFoundation\JsonResponse;

final class AjaxHandlerRegistryTest extends TestCase
{
    private AjaxHandlerRegistry $registry;

    protected function setUp(): void
    {
        if (!function_exists('add_action')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->registry = new AjaxHandlerRegistry();
    }

    #[Test]
    public function publicAccessRegistersBothHooks(): void
    {
        $subscriber = new class {
            #[AjaxHandler(action: 'test_public')]
            public function handle(): void {}
        };

        $this->registry->register($subscriber);

        self::assertNotFalse(has_action('wp_ajax_test_public'));
        self::assertNotFalse(has_action('wp_ajax_nopriv_test_public'));
    }

    #[Test]
    public function authenticatedAccessRegistersOnlyPrivHook(): void
    {
        $subscriber = new class {
            #[AjaxHandler(action: 'test_auth', access: Access::Authenticated)]
            public function handle(): void {}
        };

        $this->registry->register($subscriber);

        self::assertNotFalse(has_action('wp_ajax_test_auth'));
        self::assertFalse(has_action('wp_ajax_nopriv_test_auth'));
    }

    #[Test]
    public function guestAccessRegistersOnlyNoprivHook(): void
    {
        $subscriber = new class {
            #[AjaxHandler(action: 'test_guest', access: Access::Guest)]
            public function handle(): void {}
        };

        $this->registry->register($subscriber);

        self::assertFalse(has_action('wp_ajax_test_guest'));
        self::assertNotFalse(has_action('wp_ajax_nopriv_test_guest'));
    }

    #[Test]
    public function customPriorityIsRespected(): void
    {
        $subscriber = new class {
            #[AjaxHandler(action: 'test_priority', priority: 5)]
            public function handle(): void {}
        };

        $this->registry->register($subscriber);

        self::assertNotFalse(has_action('wp_ajax_test_priority'));
    }

    #[Test]
    public function multipleHandlersOnSameMethod(): void
    {
        $subscriber = new class {
            #[AjaxHandler(action: 'action_a')]
            #[AjaxHandler(action: 'action_b', access: Access::Authenticated)]
            public function handle(): void {}
        };

        $this->registry->register($subscriber);

        self::assertNotFalse(has_action('wp_ajax_action_a'));
        self::assertNotFalse(has_action('wp_ajax_nopriv_action_a'));
        self::assertNotFalse(has_action('wp_ajax_action_b'));
        self::assertFalse(has_action('wp_ajax_nopriv_action_b'));
    }

    #[Test]
    public function callbackInvokesHandlerMethod(): void
    {
        $subscriber = new class {
            public bool $called = false;

            #[AjaxHandler(action: 'test_invoke')]
            public function handle(): void
            {
                $this->called = true;
            }
        };

        $this->registry->register($subscriber);

        $this->executeAjaxCallback('wp_ajax_test_invoke');

        self::assertTrue($subscriber->called);
    }

    #[Test]
    public function callbackSendsJsonResponseOnSuccess(): void
    {
        $subscriber = new class {
            #[AjaxHandler(action: 'test_json_success')]
            public function handle(): JsonResponse
            {
                return new JsonResponse(['msg' => 'ok']);
            }
        };

        $this->registry->register($subscriber);

        $output = $this->executeAjaxCallback('wp_ajax_test_json_success');
        $decoded = json_decode($output, true);

        self::assertTrue($decoded['success']);
        self::assertSame(['msg' => 'ok'], $decoded['data']);
    }

    #[Test]
    public function callbackDeniesAccessOnInsufficientCapability(): void
    {
        $subscriber = new class {
            public bool $called = false;

            #[AjaxHandler(action: 'test_cap', capability: 'manage_options')]
            public function handle(): JsonResponse
            {
                $this->called = true;

                return new JsonResponse(['ok' => true]);
            }
        };

        $this->registry->register($subscriber);

        // Default test user does not have manage_options
        wp_set_current_user(0);
        $output = $this->executeAjaxCallback('wp_ajax_test_cap');
        $decoded = json_decode($output, true);

        self::assertFalse($subscriber->called);
        self::assertFalse($decoded['success']);
        self::assertSame('Insufficient permissions.', $decoded['data']);
    }

    #[Test]
    public function callbackAllowsAccessWithSufficientCapability(): void
    {
        $subscriber = new class {
            public bool $called = false;

            #[AjaxHandler(action: 'test_cap_ok', capability: 'manage_options')]
            public function handle(): JsonResponse
            {
                $this->called = true;

                return new JsonResponse(['ok' => true]);
            }
        };

        $this->registry->register($subscriber);

        wp_set_current_user(1);
        $output = $this->executeAjaxCallback('wp_ajax_test_cap_ok');
        $decoded = json_decode($output, true);

        self::assertTrue($subscriber->called);
        self::assertTrue($decoded['success']);
    }

    #[Test]
    public function callbackVerifiesReferer(): void
    {
        $subscriber = new class {
            public bool $called = false;

            #[AjaxHandler(action: 'test_referer', checkReferer: 'my_nonce')]
            public function handle(): void
            {
                $this->called = true;
            }
        };

        $this->registry->register($subscriber);

        // Set valid nonce in request
        $_REQUEST['_ajax_nonce'] = wp_create_nonce('my_nonce');

        try {
            $this->executeAjaxCallback('wp_ajax_test_referer');
        } finally {
            unset($_REQUEST['_ajax_nonce']);
        }

        self::assertTrue($subscriber->called);
    }

    /**
     * Execute the registered callback for a given hook, capturing output.
     *
     * Sets up wp_die handler to throw WPAjaxDieContinueException
     * so that wp_send_json_* doesn't terminate the process.
     */
    private function executeAjaxCallback(string $hook): string
    {
        add_filter('wp_die_ajax_handler', static fn(): string => self::class . '::ajaxDieHandler');
        add_filter('wp_doing_ajax', static fn(): bool => true);

        ob_start();

        try {
            do_action($hook);
        } catch (\WPAjaxDieContinueException|\WPAjaxDieStopException) {
            // Expected: wp_send_json triggers wp_die which throws
        }

        $output = ob_get_clean();

        remove_filter('wp_die_ajax_handler', static fn(): string => self::class . '::ajaxDieHandler');
        remove_filter('wp_doing_ajax', static fn(): bool => true);

        return $output ?: '';
    }

    /**
     * wp_die handler that throws an exception instead of terminating.
     */
    public static function ajaxDieHandler(string $message = ''): void
    {
        if (ob_get_length() > 0) {
            throw new \WPAjaxDieContinueException($message);
        }

        throw new \WPAjaxDieStopException($message);
    }
}
