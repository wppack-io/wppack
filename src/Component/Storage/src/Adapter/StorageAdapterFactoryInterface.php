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

namespace WpPack\Component\Storage\Adapter;

interface StorageAdapterFactoryInterface
{
    /** @param array<string, mixed> $options */
    public function create(Dsn $dsn, array $options = []): StorageAdapterInterface;

    public function supports(Dsn $dsn): bool;
}
