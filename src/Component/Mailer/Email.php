<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer;

use WpPack\Component\Mailer\Header\Headers;

class Email
{
    public const PRIORITY_HIGHEST = 1;
    public const PRIORITY_HIGH = 2;
    public const PRIORITY_NORMAL = 3;
    public const PRIORITY_LOW = 4;
    public const PRIORITY_LOWEST = 5;

    private ?Address $from = null;
    /** @var Address[] */
    private array $to = [];
    /** @var Address[] */
    private array $cc = [];
    /** @var Address[] */
    private array $bcc = [];
    /** @var Address[] */
    private array $replyTo = [];
    private ?string $subject = null;
    private ?string $text = null;
    private ?string $html = null;
    /** @var Attachment[] */
    private array $attachments = [];
    private Headers $headers;
    private ?Address $returnPath = null;
    private int $priority = self::PRIORITY_NORMAL;

    public function __construct()
    {
        $this->headers = new Headers();
    }

    public function from(string|Address $address, string $name = ''): static
    {
        $this->from = $address instanceof Address ? $address : new Address($address, $name);

        return $this;
    }

    public function to(string|Address ...$addresses): static
    {
        $this->to = array_map(self::resolveAddress(...), $addresses);
        return $this;
    }

    public function addTo(string|Address ...$addresses): static
    {
        foreach ($addresses as $address) {
            $this->to[] = self::resolveAddress($address);
        }
        return $this;
    }

    public function cc(string|Address ...$addresses): static
    {
        $this->cc = array_map(self::resolveAddress(...), $addresses);
        return $this;
    }

    public function addCc(string|Address ...$addresses): static
    {
        foreach ($addresses as $address) {
            $this->cc[] = self::resolveAddress($address);
        }
        return $this;
    }

    public function bcc(string|Address ...$addresses): static
    {
        $this->bcc = array_map(self::resolveAddress(...), $addresses);
        return $this;
    }

    public function addBcc(string|Address ...$addresses): static
    {
        foreach ($addresses as $address) {
            $this->bcc[] = self::resolveAddress($address);
        }
        return $this;
    }

    public function replyTo(string|Address ...$addresses): static
    {
        $this->replyTo = array_map(self::resolveAddress(...), $addresses);
        return $this;
    }

    public function addReplyTo(string|Address ...$addresses): static
    {
        foreach ($addresses as $address) {
            $this->replyTo[] = self::resolveAddress($address);
        }
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function text(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function html(string $html): static
    {
        $this->html = $html;
        return $this;
    }

    public function attach(string $path, ?string $name = null, ?string $contentType = null): static
    {
        $this->attachments[] = new Attachment($path, $name, $contentType);
        return $this;
    }

    public function embed(string $path, string $cid, ?string $contentType = null): static
    {
        $this->attachments[] = new Attachment($path, $cid, $contentType, inline: true);
        return $this;
    }

    public function priority(int $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function addHeader(string $name, string $value): static
    {
        $this->headers->add($name, $value);
        return $this;
    }

    public function returnPath(string|Address $address): static
    {
        $this->returnPath = self::resolveAddress($address);
        return $this;
    }

    public function getFrom(): ?Address
    {
        return $this->from;
    }

    /** @return Address[] */
    public function getTo(): array
    {
        return $this->to;
    }

    /** @return Address[] */
    public function getCc(): array
    {
        return $this->cc;
    }

    /** @return Address[] */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    /** @return Address[] */
    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function getHtml(): ?string
    {
        return $this->html;
    }

    /** @return Attachment[] */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getHeaders(): Headers
    {
        return $this->headers;
    }

    public function getReturnPath(): ?Address
    {
        return $this->returnPath;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    private static function resolveAddress(string|Address $address): Address
    {
        return $address instanceof Address ? $address : new Address($address);
    }
}
