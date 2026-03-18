<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\OEmbed\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class OembedTtlFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('oembed_ttl', $priority);
    }
}
