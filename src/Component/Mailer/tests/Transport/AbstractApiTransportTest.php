<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\PhpMailer;
use WpPack\Component\Mailer\Transport\AbstractApiTransport;

final class AbstractApiTransportTest extends TestCase
{
    private function createTransport(string $messageId = 'test-message-id'): AbstractApiTransport
    {
        return new class ($messageId) extends AbstractApiTransport {
            public function __construct(
                private readonly string $messageId,
            ) {}

            protected function doSendApi(PhpMailer $phpMailer): string
            {
                return $this->messageId;
            }

            public function getName(): string
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
            public function testDoSend(PhpMailer $phpMailer): void
            {
                $this->doSend($phpMailer);
            }
        };
    }

    #[Test]
    public function doSendSetsMessageIdWithAngleBrackets(): void
    {
        $transport = $this->createTransport('abc-123-def');
        $phpMailer = new PhpMailer(true);

        $transport->testDoSend($phpMailer);

        self::assertSame('<abc-123-def>', $phpMailer->getLastMessageID());
    }

    #[Test]
    public function doSendSetsMessageIdFromDoSendApi(): void
    {
        $transport = $this->createTransport('unique-id-456');
        $phpMailer = new PhpMailer(true);

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

        $result = $transport->testFormatAddress(['user@example.com', '']);

        self::assertSame('user@example.com', $result);
    }

    #[Test]
    public function doSendDoesNotDoubleWrapMessageId(): void
    {
        $transport = $this->createTransport('<already-wrapped-id>');
        $phpMailer = new PhpMailer(true);

        $transport->testDoSend($phpMailer);

        self::assertSame('<already-wrapped-id>', $phpMailer->getLastMessageID());
    }

    #[Test]
    public function sendRegistersTransport(): void
    {
        $transport = $this->createTransport('msg-id');
        $phpMailer = new PhpMailer(true);
        $phpMailer->setTransport($transport);

        self::assertSame('test-api', $phpMailer->Mailer);
    }

    #[Test]
    public function getNameReturnsExpected(): void
    {
        $transport = $this->createTransport();

        self::assertSame('test-api', $transport->getName());
    }
}
