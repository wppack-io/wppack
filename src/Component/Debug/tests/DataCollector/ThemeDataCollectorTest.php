<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\ThemeDataCollector;

final class ThemeDataCollectorTest extends TestCase
{
    private ThemeDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new ThemeDataCollector();
    }

    #[Test]
    public function getNameReturnsTheme(): void
    {
        self::assertSame('theme', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsTheme(): void
    {
        self::assertSame('Theme', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressReturnsDefaults(): void
    {
        if (function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('', $data['name']);
        self::assertSame('', $data['version']);
        self::assertFalse($data['is_child_theme']);
        self::assertFalse($data['is_block_theme']);
        self::assertSame('', $data['template_file']);
        self::assertSame([], $data['template_parts']);
        self::assertSame([], $data['body_classes']);
        self::assertSame([], $data['conditional_tags']);
        self::assertSame([], $data['enqueued_styles']);
        self::assertSame([], $data['enqueued_scripts']);
        self::assertSame(0.0, $data['setup_time']);
        self::assertSame(0.0, $data['render_time']);
        self::assertSame(0, $data['hook_count']);
        self::assertSame(0, $data['listener_count']);
        self::assertSame(0.0, $data['hook_time']);
        self::assertSame([], $data['hooks']);
    }

    #[Test]
    public function getBadgeValueReturnsEmptyString(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['name' => 'Twenty Twenty-Four']);

        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeValueReturnsEmptyWhenNoData(): void
    {
        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getBadgeColor());
    }

    #[Test]
    public function captureSetupTimeMeasuresDuration(): void
    {
        $this->collector->captureSetupStart();
        usleep(1000); // 1ms
        $this->collector->captureSetupEnd();

        $reflection = new \ReflectionProperty($this->collector, 'setupTime');
        $setupTime = $reflection->getValue($this->collector);

        self::assertGreaterThan(0.0, $setupTime);
    }

    #[Test]
    public function captureRenderTimeMeasuresDuration(): void
    {
        $this->collector->captureRenderStart();
        usleep(1000); // 1ms
        $this->collector->captureRenderEnd();

        $reflection = new \ReflectionProperty($this->collector, 'renderTime');
        $renderTime = $reflection->getValue($this->collector);

        self::assertGreaterThan(0.0, $renderTime);
    }

    #[Test]
    public function captureTemplateIncludeStoresFileAndReturnsIt(): void
    {
        $template = '/var/www/html/wp-content/themes/flavor/single.php';
        $result = $this->collector->captureTemplateInclude($template);

        self::assertSame($template, $result);

        $reflection = new \ReflectionProperty($this->collector, 'templateFile');
        self::assertSame($template, $reflection->getValue($this->collector));
    }

    #[Test]
    public function captureTemplatePartAppends(): void
    {
        $this->collector->captureTemplatePart('header');
        $this->collector->captureTemplatePart('footer');

        $reflection = new \ReflectionProperty($this->collector, 'templateParts');
        self::assertSame(['header', 'footer'], $reflection->getValue($this->collector));
    }

    #[Test]
    public function captureBodyClassStoresAndReturnsClasses(): void
    {
        $classes = ['single', 'single-post', 'logged-in'];
        $result = $this->collector->captureBodyClass($classes);

        self::assertSame($classes, $result);

        $reflection = new \ReflectionProperty($this->collector, 'bodyClasses');
        self::assertSame($classes, $reflection->getValue($this->collector));
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->captureTemplatePart('header');
        $this->collector->captureSetupStart();
        $this->collector->captureSetupEnd();
        $this->collector->collect();

        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();
        self::assertEmpty($this->collector->getData());

        // Internal state should be cleared
        $templatePartsRef = new \ReflectionProperty($this->collector, 'templateParts');
        self::assertSame([], $templatePartsRef->getValue($this->collector));

        $setupTimeRef = new \ReflectionProperty($this->collector, 'setupTime');
        self::assertSame(0.0, $setupTimeRef->getValue($this->collector));
    }
}
