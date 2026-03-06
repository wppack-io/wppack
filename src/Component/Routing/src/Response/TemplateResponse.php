<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Response;

use WpPack\Component\HttpFoundation\Response;

final class TemplateResponse extends Response
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $template,
        public readonly array $context = [],
        int $statusCode = 200,
        array $headers = [],
    ) {
        parent::__construct('', $statusCode, $headers);
    }
}
