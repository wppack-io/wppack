<?php

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
