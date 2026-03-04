<?php

declare(strict_types=1);

namespace WpPack\Component\OEmbed;

final class OEmbedProviderDefinition
{
    public function __construct(
        public readonly string $format,
        public readonly string $endpoint,
        public readonly bool $regex = false,
    ) {}
}
