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

class JsonResponse extends Response
{
    public readonly mixed $data;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        mixed $data = null,
        int $statusCode = 200,
        array $headers = [],
        int $encodingOptions = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE,
    ) {
        $this->data = $data;

        $json = json_encode($data, $encodingOptions);

        if ($json === false) {
            throw new \InvalidArgumentException('Failed to encode data to JSON: ' . json_last_error_msg());
        }

        $headers = array_merge(['Content-Type' => 'application/json'], $headers);

        parent::__construct($json, $statusCode, $headers);
    }
}
