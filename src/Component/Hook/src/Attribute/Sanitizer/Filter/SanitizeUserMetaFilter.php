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

namespace WPPack\Component\Hook\Attribute\Sanitizer\Filter;

use WPPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SanitizeUserMetaFilter extends Filter
{
    public function __construct(string $metaKey, int $priority = 10)
    {
        parent::__construct("sanitize_user_meta_{$metaKey}", $priority);
    }
}
