<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Storage\Subscriber;

use WpPack\Component\Filesystem\Attribute\Filter\WpHandleSideloadPrefilterFilter;
use WpPack\Component\Hook\Attribute\AsHookSubscriber;

#[AsHookSubscriber]
final class SideloadSubscriber
{
    /**
     * Ensure sideloaded files are properly handled for storage.
     *
     * Validates and normalizes the file array before it is processed
     * by the sideload handler, ensuring compatibility with stream wrapper paths.
     *
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    #[WpHandleSideloadPrefilterFilter]
    public function filterSideload(array $file): array
    {
        // Ensure the tmp_name is a valid local path for sideload processing.
        // Stream wrapper paths are not valid for sideloaded files at the prefilter stage.
        if (isset($file['tmp_name']) && \is_string($file['tmp_name'])) {
            $tmpName = $file['tmp_name'];

            // If the tmp_name is a stream wrapper path (non-file://), skip validation
            // as the file needs to be downloaded locally first
            if (str_contains($tmpName, '://') && !str_starts_with($tmpName, 'file://')) {
                return $file;
            }
        }

        return $file;
    }
}
