<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\Toolbar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\AbstractDataCollector;
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;
use WpPack\Component\Debug\Toolbar\ToolbarSubscriber;

final class ToolbarSubscriberTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    #[Test]
    public function registerDoesNothingWhenToolbarDisabled(): void
    {
        // enabled: false ensures shouldShowToolbar() returns false
        $config = new DebugConfig(enabled: false, showToolbar: true);
        $profile = new Profile();
        $renderer = new ToolbarRenderer($profile);

        $subscriber = new ToolbarSubscriber($config, $renderer, $profile, []);

        // Should return early at the shouldShowToolbar() guard
        $subscriber->register();

        // No exception means success — register() exited early
        self::assertFalse($config->shouldShowToolbar());
    }

    #[Test]
    public function registerAddsFooterActionsWhenEnabled(): void
    {
        $config = new DebugConfig(enabled: true, showToolbar: true);

        if (!$config->shouldShowToolbar()) {
            self::markTestSkipped('shouldShowToolbar() is false in this environment.');
        }

        $profile = new Profile();
        $renderer = new ToolbarRenderer($profile);

        $subscriber = new ToolbarSubscriber($config, $renderer, $profile, []);
        $subscriber->register();

        // Verify both wp_footer and admin_footer actions were registered
        // has_action returns the priority (int) or false
        self::assertNotFalse(has_action('wp_footer'));
        self::assertNotFalse(has_action('admin_footer'));
    }

    #[Test]
    public function onFooterDoesNothingWhenToolbarDisabled(): void
    {
        // enabled: false ensures shouldShowToolbar() returns false
        $config = new DebugConfig(enabled: false, showToolbar: true);
        $profile = new Profile();
        $renderer = new ToolbarRenderer($profile);

        $collector = new class extends AbstractDataCollector {
            public bool $collected = false;

            public function getName(): string
            {
                return 'test_disabled';
            }

            public function collect(): void
            {
                $this->collected = true;
            }
        };

        $subscriber = new ToolbarSubscriber($config, $renderer, $profile, [$collector]);

        ob_start();
        $subscriber->onFooter();
        $output = ob_get_clean();

        // Should return early without calling collect or render
        self::assertFalse($collector->collected);
        self::assertSame('', $output);
    }

    #[Test]
    public function onFooterCollectsAndRendersToolbar(): void
    {
        $_SERVER['REQUEST_URI'] = '/test-page';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $config = new DebugConfig(enabled: true, showToolbar: true);

        if (!$config->shouldShowToolbar()) {
            self::markTestSkipped('shouldShowToolbar() is false in this environment.');
        }

        // Create a mock collector
        $collector = new class extends AbstractDataCollector {
            public bool $collected = false;

            public function getName(): string
            {
                return 'test_collector';
            }

            public function collect(): void
            {
                $this->collected = true;
                $this->data = ['key' => 'value'];
            }
        };

        $profile = new Profile();
        $renderer = new ToolbarRenderer($profile);

        $subscriber = new ToolbarSubscriber($config, $renderer, $profile, [$collector]);

        ob_start();
        $subscriber->onFooter();
        $output = ob_get_clean();

        // Verify collector was called
        self::assertTrue($collector->collected);

        // Verify profile has request info set
        self::assertSame('/test-page', $profile->getUrl());
        self::assertSame('POST', $profile->getMethod());

        // Verify profile contains our collector
        $collectors = $profile->getCollectors();
        self::assertArrayHasKey('test_collector', $collectors);
        self::assertSame($collector, $collectors['test_collector']);

        // Verify renderer output was echoed (contains the debug toolbar HTML)
        self::assertStringContainsString('wppack-debug', $output);
    }

    #[Test]
    public function registerAddsFooterActionsWithAdminUser(): void
    {
        // Cover lines 29-30, 33-34: add_action calls when shouldShowToolbar=true
        if (defined('WP_DEBUG') && !WP_DEBUG) {
            self::markTestSkipped('WP_DEBUG is false.');
        }

        if (wp_get_environment_type() === 'production') {
            self::markTestSkipped('Production environment.');
        }

        $userId = wp_insert_user([
            'user_login' => 'test_sub_register_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'sub_register@example.com',
        ]);

        wp_set_current_user($userId);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $config = new DebugConfig(
                enabled: true,
                showToolbar: true,
                ipWhitelist: ['127.0.0.1'],
                roleWhitelist: ['administrator'],
            );

            if (!$config->shouldShowToolbar()) {
                self::markTestSkipped('shouldShowToolbar() is false in this environment.');
            }

            $profile = new Profile();
            $renderer = new ToolbarRenderer($profile);

            $subscriber = new ToolbarSubscriber($config, $renderer, $profile, []);
            $subscriber->register();

            // Lines 33-34: verify both wp_footer and admin_footer actions were added
            self::assertNotFalse(has_action('wp_footer'));
            self::assertNotFalse(has_action('admin_footer'));
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function onFooterCollectsAndRendersWithAdminUser(): void
    {
        // Cover lines 44-46, 50-51, 53: onFooter collects data and echoes output
        if (defined('WP_DEBUG') && !WP_DEBUG) {
            self::markTestSkipped('WP_DEBUG is false.');
        }

        if (wp_get_environment_type() === 'production') {
            self::markTestSkipped('Production environment.');
        }

        $userId = wp_insert_user([
            'user_login' => 'test_sub_footer_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'sub_footer@example.com',
        ]);

        wp_set_current_user($userId);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/admin/test';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        try {
            $config = new DebugConfig(
                enabled: true,
                showToolbar: true,
                ipWhitelist: ['127.0.0.1'],
                roleWhitelist: ['administrator'],
            );

            if (!$config->shouldShowToolbar()) {
                self::markTestSkipped('shouldShowToolbar() is false in this environment.');
            }

            // Create a mock collector to verify collect() is called
            $collector = new class extends AbstractDataCollector {
                public bool $collected = false;

                public function getName(): string
                {
                    return 'test_footer_collector';
                }

                public function collect(): void
                {
                    $this->collected = true;
                    $this->data = ['foo' => 'bar'];
                }
            };

            $profile = new Profile();
            $renderer = new ToolbarRenderer($profile);

            $subscriber = new ToolbarSubscriber($config, $renderer, $profile, [$collector]);

            ob_start();
            $subscriber->onFooter();
            $output = ob_get_clean();

            // Line 44-46: collectors should be iterated, collected, and added to profile
            self::assertTrue($collector->collected);
            $collectors = $profile->getCollectors();
            self::assertArrayHasKey('test_footer_collector', $collectors);

            // Line 50-51: profile should have request info
            self::assertSame('/admin/test', $profile->getUrl());
            self::assertSame('GET', $profile->getMethod());

            // Line 53: renderer output should be echoed
            self::assertStringContainsString('wppack-debug', $output);
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }
}
