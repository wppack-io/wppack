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

namespace WpPack\Component\Debug\Handler;

use WpPack\Component\Debug\DataCollector\LoggerDataCollector;
use WpPack\Component\Logger\Handler\HandlerInterface;

final class DebugHandler implements HandlerInterface
{
    public function __construct(
        private readonly LoggerDataCollector $collector,
    ) {}

    public function isHandling(string $level): bool
    {
        return true;
    }

    public function handle(string $level, string $message, array $context): void
    {
        $channel = $context['_channel'] ?? 'app';
        $filteredContext = array_diff_key($context, ['_channel' => true]);

        // Skip backtrace if _file is already provided (e.g. from ErrorHandler, ErrorLogInterceptor)
        if (!isset($filteredContext['_file'])) {
            $file = '';
            $line = 0;
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
            foreach ($trace as $frame) {
                $frameFile = $frame['file'] ?? '';
                if ($frameFile !== ''
                    && !str_contains($frameFile, '/Logger/src/')
                    && !str_contains($frameFile, '/Debug/src/')) {
                    $file = $frameFile;
                    $line = $frame['line'] ?? 0;
                    break;
                }
            }

            $filteredContext['_file'] = $file;
            $filteredContext['_line'] = $line;
        }

        $this->collector->log($level, $message, $filteredContext, $channel);
    }
}
