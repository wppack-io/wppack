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

use WPPack\Component\Scim\Schema\ScimConstants;

final class ListResponseSerializer
{
    private function __construct() {}

    /**
     * @param list<array<string, mixed>> $resources
     *
     * @return array<string, mixed>
     */
    public static function serialize(array $resources, int $totalResults, int $startIndex = 1, ?int $itemsPerPage = null): array
    {
        return [
            'schemas' => [ScimConstants::LIST_RESPONSE_SCHEMA],
            'totalResults' => $totalResults,
            'startIndex' => $startIndex,
            'itemsPerPage' => $itemsPerPage ?? \count($resources),
            'Resources' => $resources,
        ];
    }
}
