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

namespace WpPack\Component\Cache\Strategy;

interface HashStrategyInterface
{
    public function supports(string $key, string $group): bool;

    /**
     * @param array<string, mixed> $value
     * @return array<string, string> field => serialized value
     */
    public function serialize(array $value): array;

    /**
     * @param array<string, string> $fields field => serialized value
     * @return array<string, mixed>
     */
    public function deserialize(array $fields): array;
}
