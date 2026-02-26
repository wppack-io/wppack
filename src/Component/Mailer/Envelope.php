<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer;

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
