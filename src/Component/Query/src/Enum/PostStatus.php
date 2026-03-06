<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Enum;

enum PostStatus: string
{
    case Publish = 'publish';
    case Draft = 'draft';
    case Pending = 'pending';
    case Private = 'private';
    case Trash = 'trash';
    case AutoDraft = 'auto-draft';
    case Inherit = 'inherit';
    case Future = 'future';
    case Any = 'any';
}
