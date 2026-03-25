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
