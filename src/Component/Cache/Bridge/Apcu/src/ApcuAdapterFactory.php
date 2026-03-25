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

namespace WpPack\Component\Cache\Bridge\Apcu;

use WpPack\Component\Cache\Adapter\AdapterFactoryInterface;
use WpPack\Component\Cache\Adapter\AdapterInterface;
use WpPack\Component\Cache\Adapter\Dsn;

final class ApcuAdapterFactory implements AdapterFactoryInterface
{
    public function create(Dsn $dsn, array $options = []): AdapterInterface
    {
        return new ApcuAdapter();
    }

    public function supports(Dsn $dsn): bool
    {
        return $dsn->getScheme() === 'apcu'
            && \function_exists('apcu_enabled');
    }
}
