<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\ChannelResolver;

final class DefaultChannelResolver implements ChannelResolverInterface
{
    public function __construct(
        private readonly string $defaultChannel = 'php',
    ) {}

    public function resolve(string $filePath): string
    {
        return $this->defaultChannel;
    }
}
