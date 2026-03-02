<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class TemplateIncludeFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('template_include', $priority);
    }
}
