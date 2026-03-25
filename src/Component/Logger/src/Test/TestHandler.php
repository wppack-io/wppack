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

namespace WpPack\Component\Logger\Test;

use WpPack\Component\Logger\Handler\HandlerInterface;

final class TestHandler implements HandlerInterface
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $records = [];

    public function isHandling(string $level): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function handle(string $level, string $message, array $context): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function reset(): void
    {
        $this->records = [];
    }

    public function hasEmergency(string $message): bool
    {
        return $this->hasRecord($message, 'emergency');
    }

    public function hasAlert(string $message): bool
    {
        return $this->hasRecord($message, 'alert');
    }

    public function hasCritical(string $message): bool
    {
        return $this->hasRecord($message, 'critical');
    }

    public function hasError(string $message): bool
    {
        return $this->hasRecord($message, 'error');
    }

    public function hasWarning(string $message): bool
    {
        return $this->hasRecord($message, 'warning');
    }

    public function hasNotice(string $message): bool
    {
        return $this->hasRecord($message, 'notice');
    }

    public function hasInfo(string $message): bool
    {
        return $this->hasRecord($message, 'info');
    }

    public function hasDebug(string $message): bool
    {
        return $this->hasRecord($message, 'debug');
    }

    /**
     * @param array<string, mixed> $context
     */
    public function hasEmergencyThatContains(string $message, array $context = []): bool
    {
        return $this->hasRecordThatContains($message, 'emergency', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function hasAlertThatContains(string $message, array $context = []): bool
    {
        return $this->hasRecordThatContains($message, 'alert', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function hasCriticalThatContains(string $message, array $context = []): bool
    {
        return $this->hasRecordThatContains($message, 'critical', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function hasErrorThatContains(string $message, array $context = []): bool
    {
        return $this->hasRecordThatContains($message, 'error', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function hasWarningThatContains(string $message, array $context = []): bool
    {
        return $this->hasRecordThatContains($message, 'warning', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function hasNoticeThatContains(string $message, array $context = []): bool
    {
        return $this->hasRecordThatContains($message, 'notice', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function hasInfoThatContains(string $message, array $context = []): bool
    {
        return $this->hasRecordThatContains($message, 'info', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function hasDebugThatContains(string $message, array $context = []): bool
    {
        return $this->hasRecordThatContains($message, 'debug', $context);
    }

    private function hasRecord(string $message, string $level): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && $record['message'] === $message) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function hasRecordThatContains(string $message, string $level, array $context): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] !== $level) {
                continue;
            }

            if (!str_contains($record['message'], $message)) {
                continue;
            }

            if ($context !== []) {
                foreach ($context as $key => $value) {
                    if (!isset($record['context'][$key]) || $record['context'][$key] !== $value) {
                        continue 2;
                    }
                }
            }

            return true;
        }

        return false;
    }
}
