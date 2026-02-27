<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Transport;

use WpPack\Component\Mailer\PhpMailer;

interface TransportInterface
{
    public function getName(): string;

    public function send(PhpMailer $phpMailer): void;
}
