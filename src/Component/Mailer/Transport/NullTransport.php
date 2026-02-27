<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Transport;

use WpPack\Component\Mailer\PhpMailer;

final class NullTransport extends AbstractTransport
{
    protected function getMailerName(): string
    {
        return 'null';
    }

    protected function doSend(PhpMailer $phpMailer): void
    {
        // no-op
    }

    public function __toString(): string
    {
        return 'null://null';
    }
}
