<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer;

final class SentMessage
{
    public function __construct(
        private readonly Email $email,
        private readonly Envelope $envelope,
        private readonly ?string $messageId = null,
    ) {}

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }
}
