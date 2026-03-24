<?php

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
