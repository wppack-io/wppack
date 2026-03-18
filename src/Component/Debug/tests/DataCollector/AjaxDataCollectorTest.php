<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\AjaxDataCollector;

final class AjaxDataCollectorTest extends TestCase
{
    private AjaxDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new AjaxDataCollector();
    }

    #[Test]
    public function getNameReturnsAjax(): void
    {
        self::assertSame('ajax', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsAjax(): void
    {
        self::assertSame('Ajax', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutGlobalsReturnsDefaults(): void
    {
        $saved = $GLOBALS['wp_filter'] ?? null;
        unset($GLOBALS['wp_filter']);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertSame([], $data['registered_actions']);
            self::assertSame(0, $data['total_actions']);
            self::assertSame(0, $data['nopriv_count']);
        } finally {
            if ($saved !== null) {
                $GLOBALS['wp_filter'] = $saved;
            }
        }
    }

    #[Test]
    public function getIndicatorValueReturnsZero(): void
    {
        self::assertSame('0', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_actions' => 5]);
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }

    #[Test]
    public function collectWithAjaxActionsReturnsData(): void
    {

        $callback = static function (): void {};
        add_action('wp_ajax_test_debug_action', $callback, 10);
        add_action('wp_ajax_nopriv_test_debug_action', $callback, 10);
        add_action('wp_ajax_test_debug_priv_only', $callback, 10);
        add_action('wp_ajax_nopriv_test_debug_nopriv_only', $callback, 10);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertGreaterThanOrEqual(3, $data['total_actions']);
            self::assertArrayHasKey('test_debug_action', $data['registered_actions']);
            self::assertTrue($data['registered_actions']['test_debug_action']['nopriv']);
            self::assertArrayHasKey('test_debug_priv_only', $data['registered_actions']);
            self::assertFalse($data['registered_actions']['test_debug_priv_only']['nopriv']);
            self::assertArrayHasKey('test_debug_nopriv_only', $data['registered_actions']);
            self::assertTrue($data['registered_actions']['test_debug_nopriv_only']['nopriv']);
            self::assertGreaterThanOrEqual(2, $data['nopriv_count']);
        } finally {
            remove_action('wp_ajax_test_debug_action', $callback, 10);
            remove_action('wp_ajax_nopriv_test_debug_action', $callback, 10);
            remove_action('wp_ajax_test_debug_priv_only', $callback, 10);
            remove_action('wp_ajax_nopriv_test_debug_nopriv_only', $callback, 10);
        }
    }

    #[Test]
    public function collectExtractsStringCallback(): void
    {

        // phpcs:ignore WordPress.WP.AlternativeFunctions -- test only
        add_action('wp_ajax_test_debug_str_cb', 'strlen', 10);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertArrayHasKey('test_debug_str_cb', $data['registered_actions']);
            self::assertSame('strlen', $data['registered_actions']['test_debug_str_cb']['callback']);
        } finally {
            remove_action('wp_ajax_test_debug_str_cb', 'strlen', 10);
        }
    }

    #[Test]
    public function collectExtractsClosureCallback(): void
    {

        $closure = static function (): void {};
        add_action('wp_ajax_test_debug_closure_cb', $closure, 10);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertArrayHasKey('test_debug_closure_cb', $data['registered_actions']);
            self::assertSame('Closure', $data['registered_actions']['test_debug_closure_cb']['callback']);
        } finally {
            remove_action('wp_ajax_test_debug_closure_cb', $closure, 10);
        }
    }

    #[Test]
    public function collectSortsActionsByName(): void
    {

        $callback = static function (): void {};
        add_action('wp_ajax_test_debug_zzz', $callback, 10);
        add_action('wp_ajax_test_debug_aaa', $callback, 10);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            $keys = array_keys($data['registered_actions']);
            $debugKeys = array_filter($keys, static fn(string $k): bool => str_starts_with($k, 'test_debug_'));
            $debugKeys = array_values($debugKeys);

            self::assertSame('test_debug_aaa', $debugKeys[0]);
            self::assertSame('test_debug_zzz', $debugKeys[count($debugKeys) - 1]);
        } finally {
            remove_action('wp_ajax_test_debug_zzz', $callback, 10);
            remove_action('wp_ajax_test_debug_aaa', $callback, 10);
        }
    }
}
