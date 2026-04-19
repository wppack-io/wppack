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

interface TransportFactoryInterface
{
    /**
     * @return list<TransportDefinition>
     */
    public static function definitions(): array;

    public function create(Dsn $dsn): TransportInterface;

    public function supports(Dsn $dsn): bool;
}
