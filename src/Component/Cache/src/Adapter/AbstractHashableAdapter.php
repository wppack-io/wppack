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

abstract class AbstractHashableAdapter extends AbstractAdapter implements HashableAdapterInterface
{
    /**
     * @return array<string, string>
     */
    abstract protected function doHashGetAll(string $key): array;

    abstract protected function doHashGet(string $key, string $field): ?string;

    /**
     * @param array<string, string> $fields
     */
    abstract protected function doHashSetMultiple(string $key, array $fields): bool;

    /**
     * @param list<string> $fields
     */
    abstract protected function doHashDeleteMultiple(string $key, array $fields): bool;

    abstract protected function doHashDelete(string $key): bool;

    public function hashGetAll(string $key): array
    {
        return $this->execute(fn(): array => $this->doHashGetAll($key));
    }

    public function hashGet(string $key, string $field): ?string
    {
        return $this->execute(fn(): ?string => $this->doHashGet($key, $field));
    }

    public function hashSetMultiple(string $key, array $fields): bool
    {
        return $this->execute(fn(): bool => $this->doHashSetMultiple($key, $fields));
    }

    public function hashDeleteMultiple(string $key, array $fields): bool
    {
        return $this->execute(fn(): bool => $this->doHashDeleteMultiple($key, $fields));
    }

    public function hashDelete(string $key): bool
    {
        return $this->execute(fn(): bool => $this->doHashDelete($key));
    }
}
