<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Handler;

use WpPack\Component\Logger\Logger;

final class ErrorLogHandler implements HandlerInterface
{
    private readonly int $minLevelSeverity;

    public function __construct(
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
        $filteredContext = array_diff_key($context, ['_channel' => true]);

        $formatted = sprintf(
            '[%s.%s] %s',
            $channel,
            strtoupper($level),
            $message,
        );

        if ($filteredContext !== []) {
            try {
                $json = json_encode($filteredContext, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
                $formatted .= ' ' . $json;
            } catch (\JsonException) {
                $formatted .= ' [context not serializable]';
            }
        }

        error_log($formatted);
    }
}
