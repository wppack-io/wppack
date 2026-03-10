<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\Toolbar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;

final class ToolbarRendererTest extends TestCase
{
    private ToolbarRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ToolbarRenderer();
    }

    #[Test]
    public function renderOutputContainsWppackDebugDivId(): void
    {
        $profile = $this->createProfileWithCollectors();

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('id="wppack-debug"', $html);
    }

    #[Test]
    public function renderOutputContainsStyleTagWithCss(): void
    {
        $profile = $this->createProfileWithCollectors();

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('<style>', $html);
        self::assertStringContainsString('</style>', $html);
        // Verify it contains actual CSS rules
        self::assertStringContainsString('#wppack-debug', $html);
    }

    #[Test]
    public function renderOutputContainsScriptTagWithJs(): void
    {
        $profile = $this->createProfileWithCollectors();

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('<script>', $html);
        self::assertStringContainsString('</script>', $html);
    }

    #[Test]
    public function renderOutputContainsBadgeForEachCollector(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('memory', 'Memory', '12.3 MB', 'green'));
        $profile->addCollector($this->createCollector('time', 'Time', '150 ms', 'yellow'));
        $profile->addCollector($this->createCollector('database', 'Database', '25', 'default'));

        $html = $this->renderer->render($profile);

        // Each collector should have a badge button with data-panel attribute
        self::assertStringContainsString('data-panel="memory"', $html);
        self::assertStringContainsString('data-panel="time"', $html);
        self::assertStringContainsString('data-panel="database"', $html);
    }

    #[Test]
    public function renderPanelsContainCollectorLabels(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('memory', 'Memory', '8 MB', 'green'));
        $profile->addCollector($this->createCollector('request', 'Request', 'GET 200', 'default'));

        $html = $this->renderer->render($profile);

        // Panels should contain the collector labels
        self::assertStringContainsString('Memory', $html);
        self::assertStringContainsString('Request', $html);
    }

    #[Test]
    public function renderOutputIsProperlyEscaped(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector(
            'test',
            'Test <script>alert("xss")</script>',
            '<img onerror=alert(1)>',
            'default',
        ));

        $html = $this->renderer->render($profile);

        // Raw HTML tags should not appear - they should be escaped
        self::assertStringNotContainsString('<script>alert("xss")</script>', $html);
        self::assertStringNotContainsString('<img onerror=alert(1)>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function renderOutputContainsBadgeValues(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('memory', 'Memory', '42.5 MB', 'yellow'));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('42.5 MB', $html);
    }

    #[Test]
    public function renderOutputContainsPanelIdsForEachCollector(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('cache', 'Cache', '95%', 'green'));
        $profile->addCollector($this->createCollector('wordpress', 'WordPress', '6.4', 'default'));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('id="wpd-panel-cache"', $html);
        self::assertStringContainsString('id="wpd-panel-wordpress"', $html);
    }

    private function createProfileWithCollectors(): Profile
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('memory', 'Memory', '10 MB', 'green'));
        $profile->addCollector($this->createCollector('time', 'Time', '120 ms', 'default'));

        return $profile;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createCollector(
        string $name,
        string $label,
        string $badgeValue,
        string $badgeColor,
        array $data = [],
    ): DataCollectorInterface {
        return new class ($name, $label, $badgeValue, $badgeColor, $data) implements DataCollectorInterface {
            /**
             * @param array<string, mixed> $data
             */
            public function __construct(
                private readonly string $name,
                private readonly string $label,
                private readonly string $badgeValue,
                private readonly string $badgeColor,
                private readonly array $data,
            ) {}

            public function getName(): string
            {
                return $this->name;
            }

            public function collect(): void {}

            public function getData(): array
            {
                return $this->data;
            }

            public function getLabel(): string
            {
                return $this->label;
            }

            public function getBadgeValue(): string
            {
                return $this->badgeValue;
            }

            public function getBadgeColor(): string
            {
                return $this->badgeColor;
            }

            public function reset(): void {}
        };
    }
}
