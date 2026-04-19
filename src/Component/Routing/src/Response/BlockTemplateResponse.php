<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Routing\Response;

use WPPack\Component\HttpFoundation\Response;

final class BlockTemplateResponse extends Response
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $slug,
        public readonly array $context = [],
        int $statusCode = 200,
        array $headers = [],
    ) {
        parent::__construct('', $statusCode, $headers);
    }
}
