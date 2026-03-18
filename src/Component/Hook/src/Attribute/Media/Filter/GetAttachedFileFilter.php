<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Media\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class GetAttachedFileFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('get_attached_file', $priority);
    }
}
