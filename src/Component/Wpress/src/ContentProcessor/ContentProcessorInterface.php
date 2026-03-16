<?php

declare(strict_types=1);

namespace WpPack\Component\Wpress\ContentProcessor;

interface ContentProcessorInterface
{
    public function decode(string $data): string;

    public function encode(string $data): string;
}
