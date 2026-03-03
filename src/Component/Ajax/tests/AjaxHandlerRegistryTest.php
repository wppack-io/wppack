<?php

declare(strict_types=1);

namespace WpPack\Component\Ajax\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Ajax\Access;
use WpPack\Component\Ajax\AjaxHandlerRegistry;
use WpPack\Component\Ajax\Attribute\AjaxHandler;

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
}
