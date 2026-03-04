<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Response;

abstract class RouteResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $statusCode = 200,
        public readonly array $headers = [],
    ) {}
}
