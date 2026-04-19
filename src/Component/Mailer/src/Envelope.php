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

final class Envelope
{
    /**
     * @param Address[] $recipients
     */
    public function __construct(
        private readonly Address $sender,
        private readonly array $recipients,
    ) {}

    public static function create(Email $email): self
    {
        $from = $email->getFrom();
        if ($from === null) {
            throw new Exception\InvalidArgumentException('An email must have a "From" address to create an envelope.');
        }

        $recipients = array_merge(
            $email->getTo(),
            $email->getCc(),
            $email->getBcc(),
        );

        if ($recipients === []) {
            throw new Exception\InvalidArgumentException('An email must have at least one recipient (To, Cc, or Bcc).');
        }

        return new self($from, $recipients);
    }

    public function getSender(): Address
    {
        return $this->sender;
    }

    /** @return Address[] */
    public function getRecipients(): array
    {
        return $this->recipients;
    }
}
