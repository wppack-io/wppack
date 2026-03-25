<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Mailer\Transport;

use WpPack\Component\Mailer\PhpMailer;

final class NullTransport extends AbstractTransport
{
    public function getName(): string
    {
        return 'null';
    }

    protected function doSend(PhpMailer $phpMailer): void
    {
        // no-op
    }

}
