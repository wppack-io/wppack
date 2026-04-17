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

namespace WpPack\Component\Database\Event;

/**
 * Dispatched after a query returns successfully, so APM integrations
 * (OpenTelemetry spans, New Relic segments, AWS X-Ray subsegments) can
 * wire up wall-clock duration and row counts without subclassing
 * WpPackWpdb or tailing SAVEQUERIES.
 *
 * $paramsSummary carries only positional type/length descriptors — never
 * the raw bound values — mirroring the logger redaction policy so
 * listeners that forward events to external systems don't accidentally
 * exfiltrate PII or secrets.
 */
final class DatabaseQueryCompletedEvent
{
    /**
     * @param array<string, string> $paramsSummary positional type descriptors (e.g. ['#0' => 'string(7)'])
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $paramsSummary,
        public readonly float $elapsedMs,
        public readonly int $rowCount,
        public readonly string $driverName,
    ) {}
}
