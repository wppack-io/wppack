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

namespace WPPack\Component\Scheduler\Message;

final readonly class ActionSchedulerMessage
{
    /**
     * @param array<mixed> $args
     */
    public function __construct(
        public string $hook,
        public array $args = [],
        public string $group = '',
        public int $actionId = 0,
    ) {}
}
