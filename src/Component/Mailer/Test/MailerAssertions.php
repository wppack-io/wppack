<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Test;

use PHPUnit\Framework\Assert;

trait MailerAssertions
{
    abstract protected function getTestMailer(): TestMailer;

    public function assertEmailSent(int $count = 1): void
    {
        Assert::assertCount($count, $this->getTestMailer()->getSentMessages(), sprintf(
            'Expected %d email(s) to be sent, but %d were sent.',
            $count,
            count($this->getTestMailer()->getSentMessages()),
        ));
    }

    public function assertEmailSentTo(string $email): void
    {
        $found = false;
        foreach ($this->getTestMailer()->getSentMessages() as $sentMessage) {
            foreach ($sentMessage->getEmail()->getTo() as $to) {
                if ($to->address === $email) {
                    $found = true;
                    break 2;
                }
            }
        }

        Assert::assertTrue($found, sprintf('No email was sent to "%s".', $email));
    }

    public function assertEmailSubject(string $subject): void
    {
        $found = false;
        foreach ($this->getTestMailer()->getSentMessages() as $sentMessage) {
            if ($sentMessage->getEmail()->getSubject() === $subject) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, sprintf('No email with subject "%s" was sent.', $subject));
    }

    public function assertNoEmailSent(): void
    {
        Assert::assertCount(0, $this->getTestMailer()->getSentMessages(), sprintf(
            'Expected no emails to be sent, but %d were sent.',
            count($this->getTestMailer()->getSentMessages()),
        ));
    }
}
