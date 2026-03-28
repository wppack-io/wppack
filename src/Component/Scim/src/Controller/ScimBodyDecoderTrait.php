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

namespace WpPack\Component\Scim\Controller;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Scim\Exception\InvalidValueException;

trait ScimBodyDecoderTrait
{
    /**
     * @return array<string, mixed>
     */
    private function decodeBody(Request $request): array
    {
        $content = $request->getContent();
        if ($content === '') {
            throw new InvalidValueException('Request body is empty.');
        }

        try {
            $body = json_decode($content, true, 20, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidValueException(sprintf('Invalid JSON: %s', $e->getMessage()));
        }

        if (!\is_array($body)) {
            throw new InvalidValueException('Request body must be a JSON object.');
        }

        return $body;
    }
}
