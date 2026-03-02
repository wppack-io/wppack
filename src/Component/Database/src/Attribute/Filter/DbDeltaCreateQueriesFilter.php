<?php

declare(strict_types=1);

namespace WpPack\Component\Database\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class DbDeltaCreateQueriesFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('dbdelta_create_queries', $priority);
    }
}
