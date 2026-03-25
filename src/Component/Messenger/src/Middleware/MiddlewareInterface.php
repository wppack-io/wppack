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

namespace WpPack\Component\Messenger\Middleware;

use WpPack\Component\Messenger\Envelope;

interface MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope;
}
