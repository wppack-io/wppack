<?php

declare(strict_types=1);

namespace WpPack\Tests\Component\Mailer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Address;
use WpPack\Component\Mailer\Attachment;
use WpPack\Component\Mailer\Email;

final class EmailTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    private function createTempFile(string $content = 'test'): string
    {
        $file = tempnam(sys_get_temp_dir(), 'wppack_email_test_');
        file_put_contents($file, $content);
        $this->tempFiles[] = $file;

        return $file;
    }

    #[Test]
    public function fluentApi(): void
    {
        $email = (new Email())
            ->from('sender@example.com', 'Sender')
            ->to('user@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->replyTo('reply@example.com')
            ->subject('Test Subject')
            ->text('Plain text')
            ->html('<h1>HTML</h1>')
            ->priority(Email::PRIORITY_HIGH);

        self::assertSame('sender@example.com', $email->getFrom()->address);
        self::assertSame('Sender', $email->getFrom()->name);
        self::assertCount(1, $email->getTo());
        self::assertSame('user@example.com', $email->getTo()[0]->address);
        self::assertCount(1, $email->getCc());
        self::assertSame('cc@example.com', $email->getCc()[0]->address);
        self::assertCount(1, $email->getBcc());
        self::assertSame('bcc@example.com', $email->getBcc()[0]->address);
        self::assertCount(1, $email->getReplyTo());
        self::assertSame('reply@example.com', $email->getReplyTo()[0]->address);
        self::assertSame('Test Subject', $email->getSubject());
        self::assertSame('Plain text', $email->getText());
        self::assertSame('<h1>HTML</h1>', $email->getHtml());
        self::assertSame(Email::PRIORITY_HIGH, $email->getPriority());
    }

    #[Test]
    public function multipleRecipients(): void
    {
        $email = (new Email())
            ->to('a@example.com', 'b@example.com');

        self::assertCount(2, $email->getTo());
        self::assertSame('a@example.com', $email->getTo()[0]->address);
        self::assertSame('b@example.com', $email->getTo()[1]->address);
    }

    #[Test]
    public function fromWithAddressObject(): void
    {
        $address = new Address('admin@example.com', 'Admin');
        $email = (new Email())->from($address);

        self::assertSame($address, $email->getFrom());
    }

    #[Test]
    public function defaultPriority(): void
    {
        $email = new Email();

        self::assertSame(Email::PRIORITY_NORMAL, $email->getPriority());
    }

    #[Test]
    public function addHeaders(): void
    {
        $email = (new Email())
            ->addHeader('X-Campaign', 'spring-sale')
            ->addHeader('X-Tracking', '12345');

        self::assertSame('spring-sale', $email->getHeaders()->get('X-Campaign'));
        self::assertSame('12345', $email->getHeaders()->get('X-Tracking'));
    }

    #[Test]
    public function returnPath(): void
    {
        $email = (new Email())->returnPath('bounce@example.com');

        self::assertSame('bounce@example.com', $email->getReturnPath()->address);
    }

    #[Test]
    public function defaultsAreNull(): void
    {
        $email = new Email();

        self::assertNull($email->getFrom());
        self::assertEmpty($email->getTo());
        self::assertEmpty($email->getCc());
        self::assertEmpty($email->getBcc());
        self::assertEmpty($email->getReplyTo());
        self::assertNull($email->getSubject());
        self::assertNull($email->getText());
        self::assertNull($email->getHtml());
        self::assertEmpty($email->getAttachments());
        self::assertNull($email->getReturnPath());
    }

    #[Test]
    public function priorityConstants(): void
    {
        self::assertSame(1, Email::PRIORITY_HIGHEST);
        self::assertSame(2, Email::PRIORITY_HIGH);
        self::assertSame(3, Email::PRIORITY_NORMAL);
        self::assertSame(4, Email::PRIORITY_LOW);
        self::assertSame(5, Email::PRIORITY_LOWEST);
    }

    #[Test]
    public function toReplacesRecipients(): void
    {
        $email = (new Email())
            ->to('first@example.com')
            ->to('second@example.com');

        self::assertCount(1, $email->getTo());
        self::assertSame('second@example.com', $email->getTo()[0]->address);
    }

    #[Test]
    public function addToAppendsRecipients(): void
    {
        $email = (new Email())
            ->to('first@example.com')
            ->addTo('second@example.com');

        self::assertCount(2, $email->getTo());
        self::assertSame('first@example.com', $email->getTo()[0]->address);
        self::assertSame('second@example.com', $email->getTo()[1]->address);
    }

    #[Test]
    public function ccReplacesRecipients(): void
    {
        $email = (new Email())
            ->cc('first@example.com')
            ->cc('second@example.com');

        self::assertCount(1, $email->getCc());
        self::assertSame('second@example.com', $email->getCc()[0]->address);
    }

    #[Test]
    public function addCcAppendsRecipients(): void
    {
        $email = (new Email())
            ->cc('first@example.com')
            ->addCc('second@example.com');

        self::assertCount(2, $email->getCc());
    }

    #[Test]
    public function attachCreatesAttachment(): void
    {
        $file = $this->createTempFile('attachment content');
        $email = (new Email())->attach($file, 'report.pdf', 'application/pdf');

        $attachments = $email->getAttachments();
        self::assertCount(1, $attachments);
        self::assertInstanceOf(Attachment::class, $attachments[0]);
        self::assertSame($file, $attachments[0]->path);
        self::assertSame('report.pdf', $attachments[0]->name);
        self::assertSame('application/pdf', $attachments[0]->contentType);
        self::assertFalse($attachments[0]->inline);
    }

    #[Test]
    public function attachWithoutNameOrContentType(): void
    {
        $file = $this->createTempFile();
        $email = (new Email())->attach($file);

        $attachments = $email->getAttachments();
        self::assertCount(1, $attachments);
        self::assertNull($attachments[0]->name);
        self::assertNull($attachments[0]->contentType);
    }

    #[Test]
    public function embedCreatesInlineAttachment(): void
    {
        $file = $this->createTempFile('image data');
        $email = (new Email())->embed($file, 'logo-cid', 'image/png');

        $attachments = $email->getAttachments();
        self::assertCount(1, $attachments);
        self::assertInstanceOf(Attachment::class, $attachments[0]);
        self::assertSame($file, $attachments[0]->path);
        self::assertSame('logo-cid', $attachments[0]->name);
        self::assertSame('image/png', $attachments[0]->contentType);
        self::assertTrue($attachments[0]->inline);
    }

    #[Test]
    public function embedWithoutContentType(): void
    {
        $file = $this->createTempFile('image data');
        $email = (new Email())->embed($file, 'banner-cid');

        $attachments = $email->getAttachments();
        self::assertCount(1, $attachments);
        self::assertTrue($attachments[0]->inline);
        self::assertSame('banner-cid', $attachments[0]->name);
        self::assertNull($attachments[0]->contentType);
    }

    #[Test]
    public function multipleAttachmentsAndEmbeds(): void
    {
        $file1 = $this->createTempFile('file 1');
        $file2 = $this->createTempFile('file 2');
        $file3 = $this->createTempFile('image');

        $email = (new Email())
            ->attach($file1, 'doc.pdf')
            ->attach($file2, 'data.csv')
            ->embed($file3, 'img-cid', 'image/jpeg');

        $attachments = $email->getAttachments();
        self::assertCount(3, $attachments);

        self::assertSame('doc.pdf', $attachments[0]->name);
        self::assertFalse($attachments[0]->inline);

        self::assertSame('data.csv', $attachments[1]->name);
        self::assertFalse($attachments[1]->inline);

        self::assertSame('img-cid', $attachments[2]->name);
        self::assertTrue($attachments[2]->inline);
    }

    #[Test]
    public function attachReturnsFluentInterface(): void
    {
        $file = $this->createTempFile();
        $email = new Email();
        $result = $email->attach($file);

        self::assertSame($email, $result);
    }

    #[Test]
    public function embedReturnsFluentInterface(): void
    {
        $file = $this->createTempFile();
        $email = new Email();
        $result = $email->embed($file, 'cid');

        self::assertSame($email, $result);
    }
}
