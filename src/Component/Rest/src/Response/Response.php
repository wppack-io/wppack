<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Response;

final class Response extends RestResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly mixed $data = null,
        int $statusCode = 200,
        array $headers = [],
    ) {
        parent::__construct($statusCode, $headers);
    }
}
