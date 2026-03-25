<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation;

class Response
{
    public readonly string $content;
    public readonly int $statusCode;

    /** @var array<string, string> */
    public readonly array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        string $content = '',
        int $statusCode = 200,
        array $headers = [],
    ) {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function send(): void
    {
        $this->sendHeaders();
        $this->sendContent();
    }

    protected function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }
    }

    protected function sendContent(): void
    {
        echo $this->content;
    }
}
