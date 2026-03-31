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

namespace WpPack\Component\Cache\Adapter;

interface AdapterFactoryInterface
{
    /** @return list<AdapterDefinition> */
    public static function definitions(): array;

    /** @param array<string, mixed> $options */
    public function create(Dsn $dsn, array $options = []): AdapterInterface;

    public function supports(Dsn $dsn): bool;
}
