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

namespace WPPack\Component\Mailer\Transport;

use WPPack\Component\Mailer\PhpMailer;

interface TransportInterface
{
    public function getName(): string;

    public function send(PhpMailer $phpMailer): void;
}
