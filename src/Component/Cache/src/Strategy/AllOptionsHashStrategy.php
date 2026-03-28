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

final class AllOptionsHashStrategy implements HashStrategyInterface
{
    public function supports(string $key, string $group): bool
    {
        return $key === 'alloptions' && $group === 'options';
    }

    public function serialize(array $value): array
    {
        $fields = [];

        foreach ($value as $name => $optionValue) {
            $fields[(string) $name] = \serialize($optionValue);
        }

        return $fields;
    }

    public function deserialize(array $fields): array
    {
        $value = [];

        foreach ($fields as $name => $serialized) {
            $value[$name] = \unserialize($serialized, ['allowed_classes' => false]);
        }

        return $value;
    }
}
