<?php

declare(strict_types=1);

namespace WpPack\Component\Shortcode\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class NoTexturizeShortcodesFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('no_texturize_shortcodes', $priority);
    }
}
