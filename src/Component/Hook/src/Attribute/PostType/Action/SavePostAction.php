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

namespace WpPack\Component\Hook\Attribute\PostType\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SavePostAction extends Action
{
    public function __construct(
        public readonly ?string $postType = null,
        int $priority = 10,
    ) {
        parent::__construct(
            $this->postType !== null ? "save_post_{$this->postType}" : 'save_post',
            $priority,
        );
    }
}
