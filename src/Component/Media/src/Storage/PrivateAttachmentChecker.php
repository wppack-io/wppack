<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
