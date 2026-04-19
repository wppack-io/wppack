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

namespace WPPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\DataCollector\ShortcodeDataCollector;

final class ShortcodeDataCollectorTest extends TestCase
{
    private ShortcodeDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new ShortcodeDataCollector();
    }

    #[Test]
    public function getNameReturnsShortcode(): void
    {
        self::assertSame('shortcode', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsShortcode(): void
    {
        self::assertSame('Shortcode', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutGlobalsReturnsDefaults(): void
    {
        $saved = $GLOBALS['shortcode_tags'] ?? null;
        unset($GLOBALS['shortcode_tags']);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertSame([], $data['shortcodes']);
            self::assertSame(0, $data['total_count']);
            self::assertSame(0, $data['used_count']);
            self::assertSame([], $data['used_shortcodes']);
            self::assertSame(0.0, $data['execution_time']);
            self::assertSame([], $data['executions']);
        } finally {
            if ($saved !== null) {
                $GLOBALS['shortcode_tags'] = $saved;
            }
        }
    }

    #[Test]
    public function capturePreAndPostShortcodeRecordsTiming(): void
    {
        // Simulate a shortcode execution cycle
        $this->collector->capturePreShortcode(false, 'gallery', [], []);
        // Small delay to ensure measurable duration
        $this->collector->capturePostShortcode('output', 'gallery', [], []);

        $reflection = new \ReflectionProperty($this->collector, 'shortcodeTimings');
        $timings = $reflection->getValue($this->collector);

        self::assertCount(1, $timings);
        self::assertSame('gallery', $timings[0]['tag']);
        self::assertArrayHasKey('start', $timings[0]);
        self::assertArrayHasKey('duration', $timings[0]);
        self::assertGreaterThanOrEqual(0.0, $timings[0]['duration']);
    }

    #[Test]
    public function getIndicatorValueReturnsTotalCount(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 12]);

        self::assertSame('12', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyWhenZero(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 0]);

        self::assertSame('', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function resetClearsDataAndTimings(): void
    {
        // Add some timing data
        $this->collector->capturePreShortcode(false, 'test', [], []);
        $this->collector->capturePostShortcode('out', 'test', [], []);

        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 5]);

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());

        $timingsReflection = new \ReflectionProperty($this->collector, 'shortcodeTimings');
        self::assertSame([], $timingsReflection->getValue($this->collector));

        $stackReflection = new \ReflectionProperty($this->collector, 'shortcodeStartStack');
        self::assertSame([], $stackReflection->getValue($this->collector));
    }

    #[Test]
    public function collectWithRegisteredShortcodesReturnsData(): void
    {

        add_shortcode('test_debug_sc', static fn(): string => 'content');

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertGreaterThanOrEqual(1, $data['total_count']);
            self::assertArrayHasKey('test_debug_sc', $data['shortcodes']);
            self::assertSame('test_debug_sc', $data['shortcodes']['test_debug_sc']['tag']);
            self::assertSame('Closure', $data['shortcodes']['test_debug_sc']['callback']);
        } finally {
            remove_shortcode('test_debug_sc');
        }
    }

    #[Test]
    public function collectFormatsStringCallback(): void
    {

        add_shortcode('test_debug_str', 'intval');

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertArrayHasKey('test_debug_str', $data['shortcodes']);
            self::assertSame('intval', $data['shortcodes']['test_debug_str']['callback']);
        } finally {
            remove_shortcode('test_debug_str');
        }
    }

    #[Test]
    public function collectWithExecutionTimingsBuildsExecutionData(): void
    {
        // Simulate shortcode execution timings via the capture methods
        $this->collector->capturePreShortcode(false, 'test_tag', [], []);
        usleep(500);
        $this->collector->capturePostShortcode('output', 'test_tag', [], []);

        // Set up shortcode_tags global with the tag
        $saved = $GLOBALS['shortcode_tags'] ?? null;
        $GLOBALS['shortcode_tags'] = ['test_tag' => static fn(): string => 'out'];

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertNotEmpty($data['executions']);
            self::assertSame('test_tag', $data['executions'][0]['tag']);
            self::assertGreaterThanOrEqual(0.0, $data['executions'][0]['duration']);
            self::assertGreaterThan(0.0, $data['execution_time']);
        } finally {
            if ($saved !== null) {
                $GLOBALS['shortcode_tags'] = $saved;
            } else {
                unset($GLOBALS['shortcode_tags']);
            }
        }
    }

    #[Test]
    public function collectDetectsUsedShortcodesInPostContent(): void
    {

        add_shortcode('test_debug_used', static fn(): string => 'used');
        add_shortcode('test_debug_unused', static fn(): string => 'unused');

        // Set up $post global with content containing the shortcode
        $savedPost = $GLOBALS['post'] ?? null;
        $post = new \stdClass();
        $post->post_content = 'Hello [test_debug_used] World';
        $GLOBALS['post'] = $post;

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertContains('test_debug_used', $data['used_shortcodes']);
            self::assertNotContains('test_debug_unused', $data['used_shortcodes']);
            self::assertTrue($data['shortcodes']['test_debug_used']['used']);
            self::assertFalse($data['shortcodes']['test_debug_unused']['used']);
            self::assertGreaterThanOrEqual(1, $data['used_count']);
        } finally {
            if ($savedPost !== null) {
                $GLOBALS['post'] = $savedPost;
            } else {
                unset($GLOBALS['post']);
            }
            remove_shortcode('test_debug_used');
            remove_shortcode('test_debug_unused');
        }
    }

    #[Test]
    public function capturePostShortcodeIgnoresMismatchedTag(): void
    {
        $this->collector->capturePreShortcode(false, 'tag_a', [], []);
        $this->collector->capturePostShortcode('output', 'tag_b', [], []);

        $reflection = new \ReflectionProperty($this->collector, 'shortcodeTimings');
        $timings = $reflection->getValue($this->collector);

        self::assertCount(0, $timings);
    }
}
