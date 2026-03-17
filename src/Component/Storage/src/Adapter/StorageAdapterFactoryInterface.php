<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Adapter;

interface StorageAdapterFactoryInterface
{
    /** @param array<string, mixed> $options */
    public function create(Dsn $dsn, array $options = []): StorageAdapterInterface;

    public function supports(Dsn $dsn): bool;
}
