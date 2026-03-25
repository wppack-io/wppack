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

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'shortcode', priority: 70)]
final class ShortcodeDataCollector extends AbstractDataCollector
{
    /** @var list<array{tag: string, start: float}> */
    private array $shortcodeStartStack = [];

    /** @var list<array{tag: string, start: float, duration: float}> */
    private array $shortcodeTimings = [];

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'shortcode';
    }

    public function getLabel(): string
    {
        return 'Shortcode';
    }

    /**
     * Capture shortcode execution start (pre_do_shortcode_tag filter).
     *
     * @param string|false $output
     * @param array<string, string> $attr
     * @param list<string> $m
     * @return string|false
     */
    public function capturePreShortcode(string|false $output, string $tag, array $attr, array $m): string|false
    {
        $this->shortcodeStartStack[] = ['tag' => $tag, 'start' => microtime(true)];

        return $output;
    }

    /**
     * Capture shortcode execution end (do_shortcode_tag filter).
     *
     * @param array<string, string> $attr
     * @param list<string> $m
     */
    public function capturePostShortcode(string $output, string $tag, array $attr, array $m): string
    {
        $entry = array_pop($this->shortcodeStartStack);
        if ($entry !== null && $entry['tag'] === $tag) {
            $this->shortcodeTimings[] = [
                'tag' => $tag,
                'start' => $entry['start'],
                'duration' => (microtime(true) - $entry['start']) * 1000,
            ];
        }

        return $output;
    }

    public function collect(): void
    {
        global $shortcode_tags;

        if (!is_array($shortcode_tags)) {
            $this->data = [
                'shortcodes' => [],
                'total_count' => 0,
                'used_count' => 0,
                'used_shortcodes' => [],
                'execution_time' => 0.0,
                'executions' => [],
            ];

            return;
        }

        $usedShortcodes = $this->detectUsedShortcodes();
        $shortcodes = [];

        foreach ($shortcode_tags as $tag => $callback) {
            $shortcodes[$tag] = [
                'tag' => $tag,
                'callback' => $this->formatCallback($callback),
                'used' => in_array($tag, $usedShortcodes, true),
            ];
        }

        // Build execution timing data
        $executionTime = 0.0;
        $executions = [];
        foreach ($this->shortcodeTimings as $timing) {
            $duration = round($timing['duration'], 2);
            $executionTime += $duration;
            $executions[] = [
                'tag' => $timing['tag'],
                'start' => $timing['start'],
                'duration' => $duration,
            ];
        }

        $this->data = [
            'shortcodes' => $shortcodes,
            'total_count' => count($shortcodes),
            'used_count' => count($usedShortcodes),
            'used_shortcodes' => $usedShortcodes,
            'execution_time' => round($executionTime, 2),
            'executions' => $executions,
        ];
    }

    public function getIndicatorValue(): string
    {
        $count = (int) ($this->data['total_count'] ?? 0);

        return $count > 0 ? (string) $count : '';
    }

    public function getIndicatorColor(): string
    {
        return 'default';
    }

    public function reset(): void
    {
        parent::reset();
        $this->shortcodeStartStack = [];
        $this->shortcodeTimings = [];
    }

    /**
     * @return list<string>
     */
    private function detectUsedShortcodes(): array
    {
        global $post, $shortcode_tags;

        if (!isset($post->post_content) || !is_array($shortcode_tags) || $shortcode_tags === []) {
            return [];
        }

        $pattern = get_shortcode_regex(array_keys($shortcode_tags));
        $used = [];

        if (preg_match_all('/' . $pattern . '/', $post->post_content, $matches) && isset($matches[2])) {
            $used = array_unique($matches[2]);
        }

        return array_values($used);
    }

    private function formatCallback(mixed $callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback) && count($callback) === 2) {
            $class = is_object($callback[0]) ? $callback[0]::class : (string) $callback[0];

            return $class . '::' . (string) $callback[1];
        }

        if ($callback instanceof \Closure) {
            return 'Closure';
        }

        return 'unknown';
    }

    private function registerHooks(): void
    {
        add_filter('pre_do_shortcode_tag', [$this, 'capturePreShortcode'], \PHP_INT_MIN, 4);
        add_filter('do_shortcode_tag', [$this, 'capturePostShortcode'], \PHP_INT_MAX, 4);
    }
}
