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

namespace WPPack\Component\Logger\Handler;

interface HandlerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function handle(string $level, string $message, array $context): void;

    public function isHandling(string $level): bool;
}
