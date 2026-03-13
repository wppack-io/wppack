<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\ChannelResolver;

interface ChannelResolverInterface
{
    public function resolve(string $filePath): string;
}
