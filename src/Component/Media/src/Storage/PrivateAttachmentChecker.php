<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Storage;

final readonly class PrivateAttachmentChecker
{
    private const META_KEY = '_wppack_private_attachment';

    public function isPrivate(int $attachmentId): bool
    {
        return (bool) get_post_meta($attachmentId, self::META_KEY, true);
    }

    public function setPrivate(int $attachmentId, bool $private): void
    {
        if ($private) {
            update_post_meta($attachmentId, self::META_KEY, '1');
        } else {
            delete_post_meta($attachmentId, self::META_KEY);
        }
    }
}
