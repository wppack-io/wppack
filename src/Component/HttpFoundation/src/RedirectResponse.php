<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation;

class RedirectResponse extends Response
{
    public readonly string $url;
    public readonly bool $safe;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        string $url,
        int $statusCode = 302,
        bool $safe = true,
        array $headers = [],
    ) {
        $this->url = $url;
        $this->safe = $safe;

        $headers = array_merge(['Location' => $url], $headers);

        parent::__construct('', $statusCode, $headers);
    }
}
