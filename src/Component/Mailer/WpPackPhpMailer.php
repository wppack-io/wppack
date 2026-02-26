<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer;

final class WpPackPhpMailer extends \PHPMailer\PHPMailer\PHPMailer
{
    /** @var array<string, \Closure(self): bool> */
    private array $customMailers = [];

    public function registerCustomMailer(string $name, \Closure $callback): void
    {
        $this->customMailers[$name] = $callback;
    }

    public function setLastMessageId(string $messageId): void
    {
        $this->lastMessageID = $messageId;
    }

    public function postSend(): bool
    {
        if (isset($this->customMailers[$this->Mailer])) {
            return ($this->customMailers[$this->Mailer])($this);
        }

        return parent::postSend();
    }
}
