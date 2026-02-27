<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Context;

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
