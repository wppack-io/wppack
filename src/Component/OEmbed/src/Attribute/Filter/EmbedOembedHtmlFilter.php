<?php

declare(strict_types=1);

namespace WpPack\Component\OEmbed\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class EmbedOembedHtmlFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('embed_oembed_html', $priority);
    }
}
