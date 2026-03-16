<?php

declare(strict_types=1);

namespace WpPack\Component\Wpress\ContentProcessor;

final class PlainContentProcessor implements ContentProcessorInterface
{
    public function decode(string $data): string
    {
        return $data;
    }

    public function encode(string $data): string
    {
        return $data;
    }
}
