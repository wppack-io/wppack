<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Response;

final class Response extends RouteResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $content = '',
        int $statusCode = 200,
        array $headers = [],
    ) {
        parent::__construct($statusCode, $headers);
    }
}
