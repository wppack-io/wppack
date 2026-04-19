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

namespace WPPack\Component\Mailer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Mailer\Exception\TransportException;
use WPPack\Component\Mailer\PhpMailer;
use WPPack\Component\Mailer\Transport\TransportInterface;

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

    #[Test]
    public function setLastMessageIdSetsProperty(): void
    {
        $phpMailer = new PhpMailer(true);

        $phpMailer->setLastMessageId('<test-id-123>');

        self::assertSame('<test-id-123>', $phpMailer->getLastMessageID());
    }

    #[Test]
    public function postSendSetsErrorAndRethrowsOnTransportException(): void
    {
        $phpMailer = new PhpMailer(true);

        $transport = new class implements TransportInterface {
            public function getName(): string
            {
                return 'failing';
            }

            public function send(PhpMailer $phpMailer): void
            {
                throw new TransportException('Transport failed');
            }
        };

        $phpMailer->setTransport($transport);

        try {
            $phpMailer->postSend();
            self::fail('Expected TransportException was not thrown');
        } catch (TransportException $e) {
            self::assertSame('Transport failed', $e->getMessage());
            self::assertStringContainsString('Transport failed', $phpMailer->ErrorInfo);
        }
    }

    #[Test]
    public function nativePostSendDelegatesToParent(): void
    {
        $phpMailer = new class (true) extends PhpMailer {
            public bool $parentCalled = false;

            protected function mailSend($header, $body)
            {
                $this->parentCalled = true;

                return true;
            }
        };
        $phpMailer->Mailer = 'mail';

        $phpMailer->nativePostSend();

        self::assertTrue($phpMailer->parentCalled);
    }
}
