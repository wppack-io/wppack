<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Tests\Fixtures;

final readonly class DummyMessage
{
    public function __construct(
        public string $content,
        public int $userId,
    ) {}
}
