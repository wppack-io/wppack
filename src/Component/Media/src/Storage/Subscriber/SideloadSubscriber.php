<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Storage\Subscriber;

use WpPack\Component\EventDispatcher\Attribute\AsEventListener;
use WpPack\Component\EventDispatcher\WordPressEvent;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;

final readonly class SideloadSubscriber
{
    public function __construct(
        private StorageConfiguration $config,
        private StorageAdapterInterface $adapter,
    ) {}

    /**
     * Move sideloaded files from system temp to storage before WordPress renames them.
     *
     * WordPress's wp_handle_sideload() uses rename() to move files from /tmp to the
     * uploads directory. Cross-stream-wrapper rename() is not supported in PHP, so we
     * upload the file to storage first and rewrite tmp_name to the stream wrapper path.
     */
    #[AsEventListener(event: 'wp_handle_sideload_prefilter')]
    public function filterSideloadPrefilter(WordPressEvent $event): void
    {
        /** @var array{tmp_name: string, name?: string, type?: string, error?: int, size?: int} $file */
        $file = $event->filterValue;

        $tmpName = $file['tmp_name'];

        // Only handle files in system temp directory
        $systemTempDir = sys_get_temp_dir();
        if (!str_starts_with(realpath($tmpName) ?: $tmpName, $systemTempDir)) {
            return;
        }

        // Generate a storage key based on the filename
        $filename = $file['name'] ?? basename($tmpName);
        $key = $this->config->prefix . '/tmp/' . wp_unique_id('sideload_') . '_' . $filename;

        // Upload to storage via adapter
        $contents = file_get_contents($tmpName);
        if ($contents === false) {
            return;
        }

        $this->adapter->write($key, $contents);

        // Remove local temp file
        @unlink($tmpName);

        // Rewrite tmp_name to stream wrapper path
        $file['tmp_name'] = sprintf(
            '%s://%s/%s',
            $this->config->protocol,
            $this->config->bucket,
            $key,
        );

        $event->filterValue = $file;
    }
}
