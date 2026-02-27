<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Handler;

interface HandlerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function handle(string $level, string $message, array $context): void;

    public function isHandling(string $level): bool;
}
