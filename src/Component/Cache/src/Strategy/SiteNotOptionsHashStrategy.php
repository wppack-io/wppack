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

final class SiteNotOptionsHashStrategy implements HashStrategyInterface
{
    private const FLAG = '1';

    public function supports(string $key, string $group): bool
    {
        return $group === 'site-options' && str_ends_with($key, ':notoptions');
    }

    public function serialize(array $value): array
    {
        $fields = [];

        foreach ($value as $name => $flag) {
            $fields[(string) $name] = self::FLAG;
        }

        return $fields;
    }

    public function deserialize(array $fields): array
    {
        $value = [];

        foreach ($fields as $name => $flag) {
            $value[$name] = true;
        }

        return $value;
    }
}
