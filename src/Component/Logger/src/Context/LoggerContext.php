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

namespace WPPack\Component\Logger\Context;

final class LoggerContext
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly array $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->context;
    }
}
