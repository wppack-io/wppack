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

namespace WpPack\Component\Scim\Mapping;

interface UserAttributeMapperInterface
{
    /**
     * Map SCIM attributes to WordPress user data and meta arrays.
     *
     * @param array<string, mixed> $scimAttributes
     *
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function toWordPress(array $scimAttributes): array;

    /**
     * Map a WordPress user to SCIM attributes.
     *
     * @return array<string, mixed>
     */
    public function toScim(\WP_User $user): array;
}
