<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Block\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class EnqueueBlockEditorAssetsAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('enqueue_block_editor_assets', $priority);
    }
}
