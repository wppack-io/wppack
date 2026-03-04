<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Response;

final class JsonResponse extends RouteResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly mixed $data,
        int $statusCode = 200,
        array $headers = [],
    ) {
        parent::__construct($statusCode, $headers);
    }
}
