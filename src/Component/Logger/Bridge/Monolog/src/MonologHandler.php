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

namespace WPPack\Component\Logger\Bridge\Monolog;

use WPPack\Component\Logger\Handler\HandlerInterface;
use WPPack\Component\Logger\Logger;

final class MonologHandler implements HandlerInterface
{
    private const INTERNAL_CONTEXT_KEYS = [
        '_channel' => true,
        '_file' => true,
        '_line' => true,
        '_type' => true,
        '_error_type' => true,
    ];

    private readonly int $minLevelSeverity;

    public function __construct(
        private readonly MonologLoggerFactory $factory,
        private readonly string $level = 'debug',
    ) {
        $this->minLevelSeverity = Logger::getLevelSeverity($this->level);
    }

    public function isHandling(string $level): bool
    {
        return Logger::getLevelSeverity($level) <= $this->minLevelSeverity;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function handle(string $level, string $message, array $context): void
    {
        $channel = $context['_channel'] ?? 'app';
        $filteredContext = array_diff_key($context, self::INTERNAL_CONTEXT_KEYS);

        $this->factory->create($channel)->log($level, $message, $filteredContext);
    }
}
