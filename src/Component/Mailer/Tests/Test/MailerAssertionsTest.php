<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests\Test;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Email;
use WpPack\Component\Mailer\Test\MailerAssertions;
use WpPack\Component\Mailer\Test\TestMailer;

final class MailerAssertionsTest extends TestCase
{
    use MailerAssertions;

    private TestMailer $testMailer;

    protected function setUp(): void
    {
        $this->testMailer = new TestMailer();
    }

    protected function getTestMailer(): TestMailer
    {
        return $this->testMailer;
    }

    #[Test]
    public function assertEmailSentPasses(): void
    {
        $this->testMailer->sendEmail(
            (new Email())->from('sender@example.com')->to('user@example.com'),
        );

        $this->assertEmailSent(1);
    }

    #[Test]
    public function assertEmailSentFailsWhenNoEmailSent(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->assertEmailSent(1);
    }

    #[Test]
    public function assertEmailSentToPasses(): void
    {
        $this->testMailer->sendEmail(
            (new Email())->from('sender@example.com')->to('user@example.com'),
        );

        $this->assertEmailSentTo('user@example.com');
    }

    #[Test]
    public function assertEmailSentToFailsForWrongRecipient(): void
    {
        $this->testMailer->sendEmail(
            (new Email())->from('sender@example.com')->to('user@example.com'),
        );

        $this->expectException(AssertionFailedError::class);
        $this->assertEmailSentTo('other@example.com');
    }

    #[Test]
    public function assertEmailSubjectPasses(): void
    {
        $this->testMailer->sendEmail(
            (new Email())->from('sender@example.com')->to('user@example.com')->subject('Welcome'),
        );

        $this->assertEmailSubject('Welcome');
    }

    #[Test]
    public function assertEmailSubjectFailsForWrongSubject(): void
    {
        $this->testMailer->sendEmail(
            (new Email())->from('sender@example.com')->to('user@example.com')->subject('Welcome'),
        );

        $this->expectException(AssertionFailedError::class);
        $this->assertEmailSubject('Goodbye');
    }

    #[Test]
    public function assertNoEmailSentPasses(): void
    {
        $this->assertNoEmailSent();
    }

    #[Test]
    public function assertNoEmailSentFailsWhenEmailSent(): void
    {
        $this->testMailer->sendEmail(
            (new Email())->from('sender@example.com')->to('user@example.com'),
        );

        $this->expectException(AssertionFailedError::class);
        $this->assertNoEmailSent();
    }
}
