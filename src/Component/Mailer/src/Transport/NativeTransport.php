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

use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\PhpMailer;

final class NativeTransport extends AbstractTransport
{
    public function getName(): string
    {
        return 'mail';
    }

    protected function doSend(PhpMailer $phpMailer): void
    {
        try {
            if (!$phpMailer->nativePostSend()) {
                throw new TransportException('Native send failed: ' . $phpMailer->ErrorInfo);
            }
        } catch (TransportException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TransportException('Native send failed: ' . $e->getMessage(), 0, $e);
        }
    }

}
