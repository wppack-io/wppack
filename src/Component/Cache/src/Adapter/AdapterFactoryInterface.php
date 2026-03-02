<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Adapter;

interface AdapterFactoryInterface
{
    /** @param array<string, mixed> $options */
    public function create(Dsn $dsn, array $options = []): AdapterInterface;

    public function supports(Dsn $dsn): bool;
}
