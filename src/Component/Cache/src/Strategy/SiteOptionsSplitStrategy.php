<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Strategy;

final class SiteOptionsSplitStrategy implements KeySplitStrategyInterface
{
    public function supports(string $key, string $group): bool
    {
        return $group === 'site-options' && str_ends_with($key, ':all');
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
            $value[$name] = \unserialize($serialized);
        }

        return $value;
    }
}
