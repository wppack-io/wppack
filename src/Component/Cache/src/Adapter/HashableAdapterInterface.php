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

interface HashableAdapterInterface extends AdapterInterface
{
    /**
     * @return array<string, string> field => serialized value
     */
    public function hashGetAll(string $key): array;

    public function hashGet(string $key, string $field): ?string;

    /**
     * @param array<string, string> $fields field => serialized value
     */
    public function hashSetMultiple(string $key, array $fields): bool;

    /**
     * @param list<string> $fields
     */
    public function hashDeleteMultiple(string $key, array $fields): bool;

    public function hashDelete(string $key): bool;
}
