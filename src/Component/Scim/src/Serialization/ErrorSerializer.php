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

namespace WPPack\Component\Scim\Serialization;

use WPPack\Component\Scim\Exception\ScimException;
use WPPack\Component\Scim\Schema\ScimConstants;

final class ErrorSerializer
{
    private function __construct() {}

    /**
     * @return array<string, mixed>
     */
    public static function fromException(ScimException $exception): array
    {
        return $exception->toScimError();
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromMessage(string $message, int $httpStatus = 400, ?string $scimType = null): array
    {
        return array_filter([
            'schemas' => [ScimConstants::ERROR_SCHEMA],
            'status' => (string) $httpStatus,
            'scimType' => $scimType,
            'detail' => $message,
        ]);
    }
}
