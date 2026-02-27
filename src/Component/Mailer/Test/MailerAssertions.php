<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Test;

use PHPUnit\Framework\Assert;
use WpPack\Component\Mailer\SentMessage;

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

    public function assertEmailSentFrom(string $email): void
    {
        $found = false;
        foreach ($this->getTestMailer()->getSentMessages() as $sentMessage) {
            $from = $sentMessage->getEmail()->getFrom();
            if ($from !== null && $from->address === $email) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, sprintf('No email was sent from "%s".', $email));
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

    public function assertEmailBodyContains(string $text): void
    {
        $found = false;
        foreach ($this->getTestMailer()->getSentMessages() as $sentMessage) {
            $body = $sentMessage->getEmail()->getText();
            if ($body !== null && str_contains($body, $text)) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, sprintf('No email body contains "%s".', $text));
    }

    public function assertEmailHtmlContains(string $html): void
    {
        $found = false;
        foreach ($this->getTestMailer()->getSentMessages() as $sentMessage) {
            $body = $sentMessage->getEmail()->getHtml();
            if ($body !== null && str_contains($body, $html)) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, sprintf('No email HTML body contains "%s".', $html));
    }

    public function assertNoEmailSent(): void
    {
        Assert::assertCount(0, $this->getTestMailer()->getSentMessages(), sprintf(
            'Expected no emails to be sent, but %d were sent.',
            count($this->getTestMailer()->getSentMessages()),
        ));
    }

    public function getLastSentEmail(): SentMessage
    {
        $messages = $this->getTestMailer()->getSentMessages();

        Assert::assertNotEmpty($messages, 'No emails have been sent.');

        return $messages[array_key_last($messages)];
    }
}
