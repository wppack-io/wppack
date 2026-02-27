<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class HttpApiDebugAction
{
    public function __construct(
        public readonly int $priority = 10,
    ) {}
}
