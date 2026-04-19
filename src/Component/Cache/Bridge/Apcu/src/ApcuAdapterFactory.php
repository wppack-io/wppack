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

namespace WPPack\Component\Cache\Bridge\Apcu;

use WPPack\Component\Cache\Adapter\AdapterDefinition;
use WPPack\Component\Cache\Adapter\AdapterFactoryInterface;
use WPPack\Component\Cache\Adapter\AdapterInterface;
use WPPack\Component\Dsn\Dsn;

final class ApcuAdapterFactory implements AdapterFactoryInterface
{
    public static function definitions(): array
    {
        return [
            new AdapterDefinition(
                scheme: 'apcu',
                label: 'APCu',
            ),
        ];
    }

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
