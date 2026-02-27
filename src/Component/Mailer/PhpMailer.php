<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer;

use PHPMailer\PHPMailer\PHPMailer as BasePhpMailer;

final class PhpMailer extends BasePhpMailer
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
