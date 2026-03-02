<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class WpGetAttachmentImageAttributesFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('wp_get_attachment_image_attributes', $priority);
    }
}
