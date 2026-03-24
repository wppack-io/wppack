<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Strategy;

final class NotOptionsSplitStrategy implements KeySplitStrategyInterface
{
    private const FLAG = '1';

    public function supports(string $key, string $group): bool
    {
        return $key === 'notoptions' && $group === 'options';
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
