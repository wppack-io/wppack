<?php

declare(strict_types=1);

namespace WpPack\Component\Filesystem\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PreWpUniqueFilenameFileListFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('pre_wp_unique_filename_file_list', $priority);
    }
}
