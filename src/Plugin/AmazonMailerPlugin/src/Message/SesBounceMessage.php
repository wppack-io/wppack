<?php

declare(strict_types=1);

namespace WpPack\Plugin\AmazonMailerPlugin\Message;

final readonly class SesBounceMessage
{
    /**
     * @param list<string> $bouncedRecipients
     */
    public function __construct(
        public string $messageId,
        public string $bounceType,
        public string $bounceSubType,
        public array $bouncedRecipients,
        public \DateTimeImmutable $timestamp,
    ) {}
}
