<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Response;

final class BinaryFileResponse extends RouteResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $path,
        public readonly ?string $filename = null,
        public readonly string $disposition = 'attachment',
        int $statusCode = 200,
        array $headers = [],
    ) {
        parent::__construct($statusCode, $headers);
    }
}
