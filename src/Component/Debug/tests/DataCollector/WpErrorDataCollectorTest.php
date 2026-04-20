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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\DataCollector\AbstractDataCollector;
use WPPack\Component\Debug\DataCollector\WpErrorDataCollector;

#[CoversClass(WpErrorDataCollector::class)]
#[CoversClass(AbstractDataCollector::class)]
final class WpErrorDataCollectorTest extends TestCase
{
    protected function tearDown(): void
    {
        remove_all_actions('wp_error_added');
        unset($GLOBALS['_wppack_wp_error_collector']);
    }

    #[Test]
    public function nameAndLabelAreDeterministic(): void
    {
        $collector = new WpErrorDataCollector();

        self::assertSame('wp_error', $collector->getName());
        self::assertSame('WP_Error', $collector->getLabel());
    }

    #[Test]
    public function registerAddsHookOnlyOnce(): void
    {
        global $wp_filter;

        $collector = new WpErrorDataCollector();

        $collector->register();
        $collector->register();
        $collector->register();

        /** @var array<string, \WP_Hook> $wp_filter */
        self::assertArrayHasKey('wp_error_added', $wp_filter);
        self::assertCount(1, $wp_filter['wp_error_added']->callbacks[\PHP_INT_MAX]);
    }

    #[Test]
    public function fromGlobalReturnsRegisteredInstance(): void
    {
        $collector = new WpErrorDataCollector();
        $GLOBALS['_wppack_wp_error_collector'] = $collector;

        self::assertSame($collector, WpErrorDataCollector::fromGlobal());
    }

    #[Test]
    public function fromGlobalFallsBackToNewInstance(): void
    {
        unset($GLOBALS['_wppack_wp_error_collector']);

        $collector = WpErrorDataCollector::fromGlobal();

        self::assertInstanceOf(WpErrorDataCollector::class, $collector);
    }

    #[Test]
    public function captureRecordsWpErrorCreation(): void
    {
        $collector = new WpErrorDataCollector();
        $collector->register();

        new \WP_Error('test_error', 'boom', ['extra' => 'data']);

        $collector->collect();
        $data = $collector->getData();

        self::assertArrayHasKey('errors', $data);
        self::assertGreaterThanOrEqual(1, $data['total_count']);
        self::assertSame('test_error', $data['errors'][0]['code']);
        self::assertSame('boom', $data['errors'][0]['message']);
    }

    #[Test]
    public function collectShortensVendorPaths(): void
    {
        $collector = new WpErrorDataCollector();
        $ref = new \ReflectionMethod($collector, 'shortenPath');
        $result = $ref->invoke($collector, '/some/deep/path/vendor/foo/bar/file.php');

        self::assertSame('.../vendor/foo/bar/file.php', $result);
    }

    #[Test]
    public function collectReturnsEmptyStringForEmptyPath(): void
    {
        $collector = new WpErrorDataCollector();
        $ref = new \ReflectionMethod($collector, 'shortenPath');

        self::assertSame('', $ref->invoke($collector, ''));
    }

    #[Test]
    public function indicatorValueReflectsErrorCount(): void
    {
        $collector = new WpErrorDataCollector();
        $collector->register();

        self::assertSame('', $collector->getIndicatorValue(), 'empty before collect');
        self::assertSame('default', $collector->getIndicatorColor());

        new \WP_Error('x', 'y');
        $collector->collect();

        self::assertSame((string) ($collector->getData()['total_count']), $collector->getIndicatorValue());
        self::assertSame('yellow', $collector->getIndicatorColor());
    }

    #[Test]
    public function resetClearsRecordedErrorsAndOrigins(): void
    {
        $collector = new WpErrorDataCollector();
        $collector->register();

        $err = new \WP_Error('x', 'y');
        $collector->collect();
        self::assertGreaterThan(0, $collector->getData()['total_count']);

        $collector->reset();

        self::assertSame([], $collector->getData());
        self::assertNull($collector->getOrigin($err));
    }

    #[Test]
    public function originIsRecordedForConstructorCall(): void
    {
        $collector = new WpErrorDataCollector();
        $collector->register();

        $err = new \WP_Error('x', 'y');

        $origin = $collector->getOrigin($err);

        self::assertIsArray($origin);
        self::assertArrayHasKey('file', $origin);
        self::assertArrayHasKey('line', $origin);
        self::assertArrayHasKey('args', $origin);
    }

    #[Test]
    public function collectFormatsDataOfVariousTypes(): void
    {
        $collector = new WpErrorDataCollector();
        $collector->register();

        new \WP_Error('no_data', 'x');
        new \WP_Error('bool_data', 'x', true);
        new \WP_Error('int_data', 'x', 42);
        new \WP_Error('long_string_data', 'x', str_repeat('a', 300));
        new \WP_Error('array_data', 'x', [1, 2, 3]);
        new \WP_Error('object_data', 'x', new \stdClass());

        $collector->collect();
        $errors = $collector->getData()['errors'];

        $byCode = [];
        foreach ($errors as $entry) {
            $byCode[$entry['code']] = $entry['data'];
        }

        self::assertSame('(none)', $byCode['no_data']);
        self::assertSame('true', $byCode['bool_data']);
        self::assertSame('42', $byCode['int_data']);
        self::assertStringEndsWith('...', $byCode['long_string_data']);
        self::assertSame('array(3)', $byCode['array_data']);
        self::assertSame('stdClass', $byCode['object_data']);
    }

    #[Test]
    public function indicatorValueIsEmptyWhenNoErrors(): void
    {
        $collector = new WpErrorDataCollector();
        $collector->collect();

        self::assertSame('', $collector->getIndicatorValue());
    }

    #[Test]
    public function abstractDataCollectorDefaultsAreUsedWhenNotOverridden(): void
    {
        $collector = new class extends AbstractDataCollector {
            public function getName(): string
            {
                return 'custom';
            }

            public function collect(): void
            {
                $this->data = ['value' => 'x'];
            }
        };

        self::assertSame('Custom', $collector->getLabel());
        self::assertSame('', $collector->getIndicatorValue());
        self::assertSame('default', $collector->getIndicatorColor());

        $collector->collect();
        self::assertSame(['value' => 'x'], $collector->getData());

        $collector->reset();
        self::assertSame([], $collector->getData());
    }
}
