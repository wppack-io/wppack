<?php

declare(strict_types=1);

namespace WpPack\Component\Filesystem\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class FilesystemMethodFileFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('filesystem_method_file', $priority);
    }
}
