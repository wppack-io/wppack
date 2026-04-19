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

namespace WPPack\Component\Hook\Attribute\Setting\Action;

use WPPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SettingsPageAction extends Action
{
    public function __construct(
        public readonly string $page,
        int $priority = 10,
    ) {
        parent::__construct("settings_page_{$this->page}", $priority);
    }
}
