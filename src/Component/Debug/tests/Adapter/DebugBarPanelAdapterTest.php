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
        if (!class_exists(\Debug_Bar_Panel::class)) {
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

        // wp_kses_post strips script tags but keeps safe HTML
        self::assertStringNotContainsString('<script>', $result);
        self::assertStringContainsString('Hello', $result);
    }

    #[Test]
    public function collectSkipsPanelsWithoutRenderOrTitleMethods(): void
    {
        if (!class_exists(\Debug_Bar_Panel::class)) {
            self::markTestSkipped('Debug_Bar_Panel class is not available.');
        }

        // Create a mock panel without render method
        $brokenPanel = new \stdClass();

        // Register the broken panel + a valid panel via filter
        $validPanel = new class ('Valid Panel') extends \Debug_Bar_Panel {
            public function render(): void
            {
                echo '<p>Valid content</p>';
            }
        };

        $callback = static fn(array $panels): array => array_merge($panels, [$brokenPanel, $validPanel]);
        add_filter('debug_bar_panels', $callback, 10, 1);

        try {
            $this->adapter->collect();
            $data = $this->adapter->getData();

            // Only valid panel should be collected
            self::assertSame(1, $data['panel_count']);
            self::assertSame('Valid Panel', $data['panels'][0]['title']);
        } finally {
            remove_filter('debug_bar_panels', $callback, 10);
        }
    }

    #[Test]
    public function collectWithPanelEmptyRender(): void
    {
        if (!class_exists(\Debug_Bar_Panel::class)) {
            self::markTestSkipped('Debug_Bar_Panel class is not available.');
        }

        // Panel with empty render output
        $emptyPanel = new class ('Empty Panel') extends \Debug_Bar_Panel {
            public function render(): void
            {
                // intentionally empty
            }
        };

        $callback = static fn(array $panels): array => array_merge($panels, [$emptyPanel]);
        add_filter('debug_bar_panels', $callback, 10, 1);

        try {
            $this->adapter->collect();
            $data = $this->adapter->getData();

            self::assertGreaterThanOrEqual(1, $data['panel_count']);

            $found = false;
            foreach ($data['panels'] as $panel) {
                if ($panel['title'] === 'Empty Panel') {
                    $found = true;
                    self::assertSame('', $panel['html']);
                }
            }
            self::assertTrue($found);
        } finally {
            remove_filter('debug_bar_panels', $callback, 10);
        }
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyWhenPanelCountZero(): void
    {
        $reflection = new \ReflectionProperty($this->adapter, 'data');
        $reflection->setValue($this->adapter, ['panel_count' => 0]);

        self::assertSame('', $this->adapter->getIndicatorValue());
    }
}
