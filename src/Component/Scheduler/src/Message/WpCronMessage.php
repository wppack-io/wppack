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

namespace WpPack\Component\Scheduler\Message;

final readonly class WpCronMessage
{
    /**
     * @param array<mixed> $args
     */
    public function __construct(
        public string $hook,
        public array $args = [],
        public string|false $schedule = false,
        public int $timestamp = 0,
    ) {}
}
