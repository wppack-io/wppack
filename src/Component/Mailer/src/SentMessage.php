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

namespace WPPack\Component\Mailer;

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
