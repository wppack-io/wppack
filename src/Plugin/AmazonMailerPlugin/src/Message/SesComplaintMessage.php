<?php

declare(strict_types=1);

namespace WpPack\Plugin\AmazonMailerPlugin\Message;

final readonly class SesComplaintMessage
{
    /**
     * @param list<string> $complainedRecipients
     */
    public function __construct(
        public string $messageId,
        public string $complaintFeedbackType,
        public array $complainedRecipients,
        public \DateTimeImmutable $timestamp,
    ) {}
}
