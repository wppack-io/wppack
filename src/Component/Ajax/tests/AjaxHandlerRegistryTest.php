<?php

declare(strict_types=1);

namespace WpPack\Component\Ajax\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Ajax\Access;
use WpPack\Component\Ajax\AjaxHandlerRegistry;
use WpPack\Component\Ajax\Attribute\Ajax;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Attribute\CurrentUser;

final class AjaxHandlerRegistryTest extends TestCase
{
    private AjaxHandlerRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new AjaxHandlerRegistry();
    }

    #[Test]
    public function publicAccessRegistersBothHooks(): void
    {
        $subscriber = new class {
            #[Ajax(action: 'test_public')]
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
            #[Ajax(action: 'test_auth', access: Access::Authenticated)]
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
            #[Ajax(action: 'test_guest', access: Access::Guest)]
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
            #[Ajax(action: 'test_priority', priority: 5)]
            public function handle(): void {}
        };

        $this->registry->register($subscriber);

        self::assertNotFalse(has_action('wp_ajax_test_priority'));
    }

    #[Test]
    public function multipleHandlersOnSameMethod(): void
    {
        $subscriber = new class {
            #[Ajax(action: 'action_a')]
            #[Ajax(action: 'action_b', access: Access::Authenticated)]
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

            #[Ajax(action: 'test_invoke')]
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
            #[Ajax(action: 'test_json_success')]
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

            #[Ajax(action: 'test_cap', capability: 'manage_options')]
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

            #[Ajax(action: 'test_cap_ok', capability: 'manage_options')]
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

            #[Ajax(action: 'test_referer', checkReferer: 'my_nonce')]
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

    #[Test]
    public function callbackInjectsRequest(): void
    {
        $subscriber = new class {
            public ?Request $receivedRequest = null;

            #[Ajax(action: 'test_inject_request')]
            public function handle(Request $request): void
            {
                $this->receivedRequest = $request;
            }
        };

        $this->registry->register($subscriber);

        $this->executeAjaxCallback('wp_ajax_test_inject_request');

        self::assertInstanceOf(Request::class, $subscriber->receivedRequest);
    }

    #[Test]
    public function callbackInjectsCurrentUser(): void
    {
        $subscriber = new class {
            public ?\WP_User $receivedUser = null;

            #[Ajax(action: 'test_inject_user')]
            public function handle(#[CurrentUser] \WP_User $user): void
            {
                $this->receivedUser = $user;
            }
        };

        $this->registry->register($subscriber);

        wp_set_current_user(1);
        $this->executeAjaxCallback('wp_ajax_test_inject_user');

        self::assertInstanceOf(\WP_User::class, $subscriber->receivedUser);
        self::assertSame(1, $subscriber->receivedUser->ID);
    }

    #[Test]
    public function callbackInjectsBothRequestAndCurrentUser(): void
    {
        $subscriber = new class {
            public ?Request $receivedRequest = null;
            public ?\WP_User $receivedUser = null;

            #[Ajax(action: 'test_inject_both')]
            public function handle(Request $request, #[CurrentUser] \WP_User $user): void
            {
                $this->receivedRequest = $request;
                $this->receivedUser = $user;
            }
        };

        $this->registry->register($subscriber);

        wp_set_current_user(1);
        $this->executeAjaxCallback('wp_ajax_test_inject_both');

        self::assertInstanceOf(Request::class, $subscriber->receivedRequest);
        self::assertInstanceOf(\WP_User::class, $subscriber->receivedUser);
        self::assertSame(1, $subscriber->receivedUser->ID);
    }

    #[Test]
    public function callbackInjectsInReversedOrder(): void
    {
        $subscriber = new class {
            public ?Request $receivedRequest = null;
            public ?\WP_User $receivedUser = null;

            #[Ajax(action: 'test_inject_reversed')]
            public function handle(#[CurrentUser] \WP_User $user, Request $request): void
            {
                $this->receivedUser = $user;
                $this->receivedRequest = $request;
            }
        };

        $this->registry->register($subscriber);

        wp_set_current_user(1);
        $this->executeAjaxCallback('wp_ajax_test_inject_reversed');

        self::assertInstanceOf(\WP_User::class, $subscriber->receivedUser);
        self::assertSame(1, $subscriber->receivedUser->ID);
        self::assertInstanceOf(Request::class, $subscriber->receivedRequest);
    }

    #[Test]
    public function registryAcceptsRequestInConstructor(): void
    {
        $request = new Request(server: ['REQUEST_URI' => '/wp-admin/admin-ajax.php', 'REQUEST_METHOD' => 'POST']);
        $registry = new AjaxHandlerRegistry($request);

        $subscriber = new class {
            public ?Request $receivedRequest = null;

            #[Ajax(action: 'test_ctor_request')]
            public function handle(Request $request): void
            {
                $this->receivedRequest = $request;
            }
        };

        $registry->register($subscriber);

        $this->executeAjaxCallback('wp_ajax_test_ctor_request');

        self::assertSame($request, $subscriber->receivedRequest);
    }

    /**
     * Execute the registered callback for a given hook, capturing output.
     *
     * Sets up wp_die handler to throw WPAjaxDieContinueException
     * so that wp_send_json_* doesn't terminate the process.
     */
    private function executeAjaxCallback(string $hook): string
    {
        $dieFilter = static fn(): string => self::class . '::ajaxDieHandler';
        $ajaxFilter = static fn(): bool => true;

        add_filter('wp_die_ajax_handler', $dieFilter);
        add_filter('wp_doing_ajax', $ajaxFilter);

        ob_start();

        try {
            do_action($hook);
        } catch (\WPAjaxDieContinueException|\WPAjaxDieStopException) {
            // Expected: wp_send_json triggers wp_die which throws
        }

        $output = ob_get_clean();

        remove_filter('wp_die_ajax_handler', $dieFilter);
        remove_filter('wp_doing_ajax', $ajaxFilter);

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
