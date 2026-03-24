<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Strategy;

interface KeySplitStrategyInterface
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
