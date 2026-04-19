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

namespace WPPack\Component\Hook\Attribute\Option\Action;

use WPPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class UpdateSiteOptionAction extends Action
{
    public function __construct(
        public readonly string $name,
        int $priority = 10,
    ) {
        parent::__construct("update_site_option_{$this->name}", $priority);
    }
}
