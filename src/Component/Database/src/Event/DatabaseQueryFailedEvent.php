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
 * Dispatched when a query fails at execute() time. Lets APM integrations
 * surface database errors as trace events without relying on log
 * scraping. $paramsSummary never contains raw bound values — just the
 * redacted positional type descriptors.
 */
final class DatabaseQueryFailedEvent
{
    /**
     * @param array<string, string> $paramsSummary positional type descriptors
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $paramsSummary,
        public readonly string $errorMessage,
        public readonly string $driverName,
    ) {}
}
