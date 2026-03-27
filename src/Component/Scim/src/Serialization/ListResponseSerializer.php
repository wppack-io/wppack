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

namespace WpPack\Component\Scim\Serialization;

use WpPack\Component\Scim\Schema\ScimConstants;

final class ListResponseSerializer
{
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
