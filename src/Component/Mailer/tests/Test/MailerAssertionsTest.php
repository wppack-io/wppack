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

namespace WPPack\Component\Mailer\Tests\Test;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Mailer\Email;
use WPPack\Component\Mailer\SentMessage;
use WPPack\Component\Mailer\Test\MailerAssertions;
use WPPack\Component\Mailer\Test\TestMailer;

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
    public function assertEmailSentFromPasses(): void
    {
        $this->testMailer->sendEmail(
            (new Email())->from('sender@example.com')->to('user@example.com'),
        );

        $this->assertEmailSentFrom('sender@example.com');
    }

    #[Test]
    public function assertEmailSentFromFailsForWrongAddress(): void
    {
        $this->testMailer->sendEmail(
            (new Email())->from('sender@example.com')->to('user@example.com'),
        );

        $this->expectException(AssertionFailedError::class);
        $this->assertEmailSentFrom('other@example.com');
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
    public function assertEmailBodyContainsPasses(): void
    {
        $this->testMailer->sendEmail(
            (new Email())->from('sender@example.com')->to('user@example.com')->text('Hello World'),
        );

        $this->assertEmailBodyContains('Hello');
    }

    #[Test]
    public function assertEmailBodyContainsFailsForMissingText(): void
    {
        $this->testMailer->sendEmail(
            (new Email())->from('sender@example.com')->to('user@example.com')->text('Hello'),
        );

        $this->expectException(AssertionFailedError::class);
        $this->assertEmailBodyContains('Missing text');
    }

    #[Test]
    public function assertEmailHtmlContainsPasses(): void
    {
        $this->testMailer->sendEmail(
            (new Email())->from('sender@example.com')->to('user@example.com')->html('<h1>Welcome</h1>'),
        );

        $this->assertEmailHtmlContains('<h1>Welcome</h1>');
    }

    #[Test]
    public function assertEmailHtmlContainsFailsForMissingHtml(): void
    {
        $this->testMailer->sendEmail(
            (new Email())->from('sender@example.com')->to('user@example.com')->text('plain'),
        );

        $this->expectException(AssertionFailedError::class);
        $this->assertEmailHtmlContains('<h1>Missing</h1>');
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

    #[Test]
    public function getLastSentEmailReturnsLastMessage(): void
    {
        $this->testMailer->sendEmail(
            (new Email())->from('sender@example.com')->to('user1@example.com')->subject('First'),
        );
        $this->testMailer->sendEmail(
            (new Email())->from('sender@example.com')->to('user2@example.com')->subject('Second'),
        );

        $last = $this->getLastSentEmail();

        self::assertInstanceOf(SentMessage::class, $last);
        self::assertSame('Second', $last->getEmail()->getSubject());
    }

    #[Test]
    public function getLastSentEmailFailsWhenNoEmails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->getLastSentEmail();
    }

    #[Test]
    public function assertEmailSentToMatchesMultipleRecipients(): void
    {
        $this->testMailer->sendEmail(
            (new Email())
                ->from('sender@example.com')
                ->to('alice@example.com', 'bob@example.com'),
        );

        $this->assertEmailSentTo('alice@example.com');
        $this->assertEmailSentTo('bob@example.com');
    }
}
