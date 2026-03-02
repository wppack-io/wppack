<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\PhpMailer;
use WpPack\Component\Mailer\Transport\TransportInterface;

final class PhpMailerTest extends TestCase
{
    #[Test]
    public function setTransportAndPostSend(): void
    {
        $phpMailer = new PhpMailer(true);
        $called = false;

        $transport = new class ($called) implements TransportInterface {
            public function __construct(private bool &$called) {}

            public function getName(): string
            {
                return 'test';
            }

            public function send(PhpMailer $phpMailer): void
            {
                $this->called = true;
            }
        };

        $phpMailer->setTransport($transport);
        $result = $phpMailer->postSend();

        self::assertTrue($called);
        self::assertTrue($result);
        self::assertSame('test', $phpMailer->Mailer);
    }

    #[Test]
    public function unregisteredMailerCallsParent(): void
    {
        $parentCalled = false;
        $phpMailer = new class (true) extends PhpMailer {
            public bool $parentCalled = false;

            protected function mailSend($header, $body)
            {
                $this->parentCalled = true;

                return true;
            }
        };
        $phpMailer->Mailer = 'mail';

        // No transport set — postSend should delegate to parent::postSend()
        $phpMailer->postSend();

        self::assertTrue($phpMailer->parentCalled);
    }

    #[Test]
    public function setTransportReplacesExisting(): void
    {
        $phpMailer = new PhpMailer(true);
        $calledTransport = '';

        $transport1 = new class ($calledTransport) implements TransportInterface {
            public function __construct(private string &$calledTransport) {}

            public function getName(): string
            {
                return 'ses';
            }

            public function send(PhpMailer $phpMailer): void
            {
                $this->calledTransport = 'ses';
            }
        };

        $transport2 = new class ($calledTransport) implements TransportInterface {
            public function __construct(private string &$calledTransport) {}

            public function getName(): string
            {
                return 'null';
            }

            public function send(PhpMailer $phpMailer): void
            {
                $this->calledTransport = 'null';
            }
        };

        $phpMailer->setTransport($transport1);
        $phpMailer->setTransport($transport2);

        // Second setTransport replaces first — only 'null' transport is active
        $phpMailer->postSend();
        self::assertSame('null', $calledTransport);
        self::assertSame('null', $phpMailer->Mailer);
    }

    #[Test]
    public function setTransportSetsMailerToTransportName(): void
    {
        $phpMailer = new PhpMailer(true);

        $transport = new class implements TransportInterface {
            public function getName(): string
            {
                return 'custom';
            }

            public function send(PhpMailer $phpMailer): void {}
        };

        $phpMailer->setTransport($transport);

        self::assertSame('custom', $phpMailer->Mailer);
    }
}
