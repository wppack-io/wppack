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

use WPPack\Component\Mailer\Exception\TransportException;
use WPPack\Component\Mailer\PhpMailer;

abstract class AbstractTransport implements TransportInterface
{
    abstract public function getName(): string;

    abstract protected function doSend(PhpMailer $phpMailer): void;

    public function send(PhpMailer $phpMailer): void
    {
        try {
            $this->doSend($phpMailer);
        } catch (TransportException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }
}
