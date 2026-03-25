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
