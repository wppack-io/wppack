<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Filesystem\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class FileIsDisplayableImageFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('file_is_displayable_image', $priority);
    }
}
