<?php

declare(strict_types=1);

// Define minimal QM_Collector stub in the global namespace if not already defined.
// This allows collect() to pass the class_exists('QM_Collector') guard in tests.

namespace {
    if (!class_exists('QM_Collector')) {
        // phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
        class QM_Collector {}
    }
}

namespace WpPack\Component\Debug\Tests\Adapter {
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;
    use WpPack\Component\Debug\Adapter\QueryMonitorCollectorAdapter;

    final class QueryMonitorCollectorAdapterTest extends TestCase
    {
        private QueryMonitorCollectorAdapter $adapter;

        protected function setUp(): void
        {
            $this->adapter = new QueryMonitorCollectorAdapter();
        }

        #[Test]
        public function getNameReturnsQueryMonitor(): void
        {
            self::assertSame('query_monitor', $this->adapter->getName());
        }

        #[Test]
        public function getLabelReturnsQueryMonitor(): void
        {
            self::assertSame('Query Monitor', $this->adapter->getLabel());
        }

        #[Test]
        public function collectWithoutApplyFiltersReturnsEmpty(): void
        {
            // WordPress is always loaded in test env; cannot test the unavailable path
            self::markTestSkipped('apply_filters is available; cannot test the unavailable path.');

            $this->adapter->collect();

            // QM_Collector exists (stub) but apply_filters is missing → early return
            self::assertSame([], $this->adapter->getData());
        }

        #[Test]
        public function collectWithQueryMonitorCollectorsCollectsData(): void
        {

            // Create a mock QM_Collector-like object with get_data() method
            $mockCollector = new class {
                /** @return array<string, mixed> */
                public function get_data(): array
                {
                    return ['queries' => 10, 'time' => 0.5];
                }
            };

            $callback = static fn(array $collectors): array => array_merge($collectors, ['db' => $mockCollector]);
            add_filter('qm/collectors', $callback, 10, 1);

            try {
                $this->adapter->collect();
                $data = $this->adapter->getData();

                self::assertArrayHasKey('collectors', $data);
                self::assertArrayHasKey('collector_count', $data);
                self::assertGreaterThanOrEqual(1, $data['collector_count']);
                self::assertArrayHasKey('db', $data['collectors']);
                self::assertSame('db', $data['collectors']['db']['id']);
                self::assertSame(['queries' => 10, 'time' => 0.5], $data['collectors']['db']['data']);
            } finally {
                remove_filter('qm/collectors', $callback, 10);
            }
        }

        #[Test]
        public function getIndicatorValueReturnsCountWhenCollectorsExist(): void
        {
            // Set data via reflection to simulate collected collectors
            $reflection = new \ReflectionProperty($this->adapter, 'data');
            $reflection->setValue($this->adapter, [
                'collectors' => [
                    'db' => ['id' => 'db', 'data' => ['queries' => 10]],
                    'http' => ['id' => 'http', 'data' => ['requests' => 3]],
                ],
                'collector_count' => 2,
            ]);

            self::assertSame('2', $this->adapter->getIndicatorValue());
        }

        #[Test]
        public function getIndicatorValueReturnsEmptyWhenNoCollectors(): void
        {
            // No data collected — collector_count defaults to 0
            self::assertSame('', $this->adapter->getIndicatorValue());
        }

        #[Test]
        public function collectWithMockQmCollectorsGathersData(): void
        {
            // Cover lines 30-31, 35, 37-40, 43-47, 50-53

            // Create mock collectors with get_data() method
            $mockDbCollector = new class {
                /** @return array<string, mixed> */
                public function get_data(): array
                {
                    return ['total_queries' => 25, 'total_time' => 1.5];
                }
            };

            $mockHttpCollector = new class {
                /**
                 * Returns data as an object (to test the is_object branch on line 46)
                 */
                public function get_data(): object
                {
                    $data = new \stdClass();
                    $data->requests = 5;
                    $data->total_time = 0.8;

                    return $data;
                }
            };

            // Collector without get_data() method — should be skipped (line 39-40)
            $mockBadCollector = new class {
                public function some_other_method(): string
                {
                    return 'nope';
                }
            };

            $callback = static fn(array $collectors): array => array_merge($collectors, [
                'qm_db' => $mockDbCollector,
                'qm_http' => $mockHttpCollector,
                'qm_bad' => $mockBadCollector,
            ]);

            add_filter('qm/collectors', $callback, 10, 1);

            try {
                $adapter = new QueryMonitorCollectorAdapter();
                $adapter->collect();
                $data = $adapter->getData();

                // Lines 50-53: data should be set with collectors and collector_count
                self::assertArrayHasKey('collectors', $data);
                self::assertArrayHasKey('collector_count', $data);

                // qm_db and qm_http should be collected, qm_bad should be skipped
                self::assertArrayHasKey('qm_db', $data['collectors']);
                self::assertArrayHasKey('qm_http', $data['collectors']);
                self::assertArrayNotHasKey('qm_bad', $data['collectors']);

                // Lines 44-46: verify data structure for array return
                self::assertSame('qm_db', $data['collectors']['qm_db']['id']);
                self::assertSame(['total_queries' => 25, 'total_time' => 1.5], $data['collectors']['qm_db']['data']);

                // Lines 46: verify data structure for object return (cast to array)
                self::assertSame('qm_http', $data['collectors']['qm_http']['id']);
                self::assertSame(['requests' => 5, 'total_time' => 0.8], $data['collectors']['qm_http']['data']);

                // collector_count should be 2 (qm_bad excluded)
                self::assertSame(2, $data['collector_count']);
            } finally {
                remove_filter('qm/collectors', $callback, 10);
            }
        }

        #[Test]
        public function collectWithEmptyCollectorsArray(): void
        {
            // Cover lines 35, 50-53 with empty collectors from apply_filters

            $callback = static fn(array $collectors): array => [];
            add_filter('qm/collectors', $callback, 10, 1);

            try {
                $adapter = new QueryMonitorCollectorAdapter();
                $adapter->collect();
                $data = $adapter->getData();

                // When no collectors registered, result should have empty collectors
                self::assertArrayHasKey('collectors', $data);
                self::assertSame([], $data['collectors']);
                self::assertSame(0, $data['collector_count']);
            } finally {
                remove_filter('qm/collectors', $callback, 10);
            }
        }

        #[Test]
        public function collectHandlesCollectorWithNullData(): void
        {
            // Cover line 46: is_array($data) ? $data : (is_object($data) ? (array) $data : [])
            // Test the case where get_data() returns neither array nor object

            $mockNullCollector = new class {
                public function get_data(): mixed
                {
                    return null;
                }
            };

            $callback = static fn(array $collectors): array => array_merge($collectors, [
                'qm_null' => $mockNullCollector,
            ]);

            add_filter('qm/collectors', $callback, 10, 1);

            try {
                $adapter = new QueryMonitorCollectorAdapter();
                $adapter->collect();
                $data = $adapter->getData();

                // null data should be converted to empty array
                self::assertArrayHasKey('qm_null', $data['collectors']);
                self::assertSame([], $data['collectors']['qm_null']['data']);
            } finally {
                remove_filter('qm/collectors', $callback, 10);
            }
        }
    }
}
