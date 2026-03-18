<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Routing\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PageRewriteRulesFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('page_rewrite_rules', $priority);
    }
}
