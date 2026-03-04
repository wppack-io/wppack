<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Response;

final class RedirectResponse extends RouteResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $url,
        int $statusCode = 302,
        public readonly bool $safe = true,
        array $headers = [],
    ) {
        parent::__construct($statusCode, $headers);
    }
}
