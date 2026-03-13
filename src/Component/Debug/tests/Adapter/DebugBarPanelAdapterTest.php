<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\Adapter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\Adapter\DebugBarPanelAdapter;

final class DebugBarPanelAdapterTest extends TestCase
{
    private DebugBarPanelAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new DebugBarPanelAdapter();
    }

    #[Test]
    public function getNameReturnsDebugBarPanel(): void
    {
        self::assertSame('debug_bar_panel', $this->adapter->getName());
    }

    #[Test]
    public function getLabelReturnsDebugBar(): void
    {
        self::assertSame('Debug Bar', $this->adapter->getLabel());
    }

    #[Test]
    public function collectWithoutDebugBarReturnsEmpty(): void
    {
        if (!class_exists(\Debug_Bar_Panel::class) || !function_exists('apply_filters')) {
            // Guard classes/functions not available — collect() returns early with empty data
            $this->adapter->collect();

            self::assertSame([], $this->adapter->getData());

            return;
        }

        // Both are available — collect with no panels registered should give empty panel list
        $this->adapter->collect();
        $data = $this->adapter->getData();

        self::assertArrayHasKey('panels', $data);
        self::assertArrayHasKey('panel_count', $data);
        self::assertSame(0, $data['panel_count']);
        self::assertSame([], $data['panels']);
    }

    #[Test]
    public function collectWithDebugBarPanelsCollectsData(): void
    {
        if (!function_exists('apply_filters')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        if (!class_exists(\Debug_Bar_Panel::class)) {
            self::markTestSkipped('Debug_Bar_Panel class is not available.');
        }

        // Register a mock panel via the debug_bar_panels filter
        $mockPanel = new class ('Test Panel') extends \Debug_Bar_Panel {
            public function render(): void
            {
                echo '<p>Panel content</p>';
            }
        };

        $callback = static fn(array $panels): array => array_merge($panels, [$mockPanel]);
        add_filter('debug_bar_panels', $callback, 10, 1);

        try {
            $this->adapter->collect();
            $data = $this->adapter->getData();

            self::assertArrayHasKey('panels', $data);
            self::assertArrayHasKey('panel_count', $data);
            self::assertGreaterThanOrEqual(1, $data['panel_count']);

            // Find our test panel in the collected data
            $found = false;
            foreach ($data['panels'] as $panel) {
                if ($panel['title'] === 'Test Panel') {
                    $found = true;
                    self::assertStringContainsString('Panel content', $panel['html']);
                }
            }
            self::assertTrue($found, 'Expected to find "Test Panel" in collected panel data.');
        } finally {
            remove_filter('debug_bar_panels', $callback, 10);
        }
    }

    #[Test]
    public function getIndicatorValueReturnsCountWhenPanelsExist(): void
    {
        // Set data via reflection to simulate collected panels
        $reflection = new \ReflectionProperty($this->adapter, 'data');
        $reflection->setValue($this->adapter, [
            'panels' => [
                ['title' => 'Panel 1', 'html' => '<p>Content</p>'],
                ['title' => 'Panel 2', 'html' => '<p>Content</p>'],
            ],
            'panel_count' => 2,
        ]);

        self::assertSame('2', $this->adapter->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyWhenNoPanels(): void
    {
        // No data collected — panel_count defaults to 0
        self::assertSame('', $this->adapter->getIndicatorValue());
    }

    #[Test]
    public function sanitizeHtmlFallsBackWhenWpKsesUnavailable(): void
    {
        $method = new \ReflectionMethod($this->adapter, 'sanitizeHtml');

        $input = '<p>Hello <script>alert("xss")</script> world</p>';

        $result = $method->invoke($this->adapter, $input);

        if (function_exists('wp_kses_post')) {
            // wp_kses_post strips script tags but keeps safe HTML
            self::assertStringNotContainsString('<script>', $result);
            self::assertStringContainsString('Hello', $result);
        } else {
            // Fallback: strip_tags + htmlspecialchars
            self::assertStringNotContainsString('<script>', $result);
            self::assertStringNotContainsString('<p>', $result);
            self::assertStringContainsString('Hello', $result);
            // The result should be HTML-escaped after strip_tags
            $expected = htmlspecialchars(strip_tags($input), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            self::assertSame($expected, $result);
        }
    }
}
