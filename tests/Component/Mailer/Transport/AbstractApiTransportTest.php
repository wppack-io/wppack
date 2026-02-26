<?php

declare(strict_types=1);

namespace WpPack\Tests\Component\Mailer\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Transport\AbstractApiTransport;
use WpPack\Component\Mailer\WpPackPhpMailer;

final class AbstractApiTransportTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            self::markTestSkipped('PHPMailer is not installed.');
        }
    }

    private function createTransport(string $messageId = 'test-message-id'): AbstractApiTransport
    {
        return new class ($messageId) extends AbstractApiTransport {
            public function __construct(
                private readonly string $messageId,
            ) {}

            protected function doSendApi(WpPackPhpMailer $phpMailer): string
            {
                return $this->messageId;
            }

            protected function getMailerName(): string
            {
                return 'test-api';
            }

            /**
             * Expose protected formatAddress for testing.
             *
             * @param array{0: string, 1: string} $addr
             */
            public function testFormatAddress(array $addr): string
            {
                return $this->formatAddress($addr);
            }

            /**
             * Expose protected doSend for testing.
             */
            public function testDoSend(WpPackPhpMailer $phpMailer): void
            {
                $this->doSend($phpMailer);
            }
        };
    }

    #[Test]
    public function doSendSetsMessageIdWithAngleBrackets(): void
    {
        $transport = $this->createTransport('abc-123-def');
        $phpMailer = new WpPackPhpMailer(true);

        $transport->testDoSend($phpMailer);

        self::assertSame('<abc-123-def>', $phpMailer->getLastMessageID());
    }

    #[Test]
    public function doSendSetsMessageIdFromDoSendApi(): void
    {
        $transport = $this->createTransport('unique-id-456');
        $phpMailer = new WpPackPhpMailer(true);

        $transport->testDoSend($phpMailer);

        self::assertSame('<unique-id-456>', $phpMailer->getLastMessageID());
    }

    #[Test]
    public function formatAddressWithEmailOnly(): void
    {
        $transport = $this->createTransport();

        $result = $transport->testFormatAddress(['user@example.com', '']);

        self::assertSame('user@example.com', $result);
    }

    #[Test]
    public function formatAddressWithName(): void
    {
        $transport = $this->createTransport();

        $result = $transport->testFormatAddress(['user@example.com', 'John Doe']);

        self::assertSame('"John Doe" <user@example.com>', $result);
    }

    #[Test]
    public function formatAddressEscapesQuotesInName(): void
    {
        $transport = $this->createTransport();

        $result = $transport->testFormatAddress(['user@example.com', 'John "Johnny" Doe']);

        self::assertSame('"John \\"Johnny\\" Doe" <user@example.com>', $result);
    }

    #[Test]
    public function formatAddressEscapesBackslashesInName(): void
    {
        $transport = $this->createTransport();

        $result = $transport->testFormatAddress(['user@example.com', 'John\\Doe']);

        self::assertSame('"John\\\\Doe" <user@example.com>', $result);
    }

    #[Test]
    public function formatAddressEscapesBothQuotesAndBackslashes(): void
    {
        $transport = $this->createTransport();

        $result = $transport->testFormatAddress(['user@example.com', 'John\\"Doe']);

        self::assertSame('"John\\\\\\"Doe" <user@example.com>', $result);
    }

    #[Test]
    public function formatAddressWithNullNameReturnsEmailOnly(): void
    {
        $transport = $this->createTransport();

        // empty() treats null as empty
        $result = $transport->testFormatAddress(['user@example.com', '']);

        self::assertSame('user@example.com', $result);
    }

    #[Test]
    public function configureRegistersCustomMailer(): void
    {
        $transport = $this->createTransport('msg-id');
        $phpMailer = new WpPackPhpMailer(true);

        $transport->configure($phpMailer);

        self::assertSame('test-api', $phpMailer->Mailer);
    }

    #[Test]
    public function toStringReturnsMailerName(): void
    {
        $transport = $this->createTransport();

        self::assertSame('test-api://', (string) $transport);
    }
}
