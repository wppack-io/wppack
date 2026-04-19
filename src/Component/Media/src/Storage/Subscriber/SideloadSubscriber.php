<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Media\Storage\Subscriber;

use Psr\Log\LoggerInterface;
use WPPack\Component\EventDispatcher\Attribute\AsEventListener;
use WPPack\Component\EventDispatcher\WordPressEvent;
use WPPack\Component\Media\Storage\StorageConfiguration;
use WPPack\Component\Storage\Adapter\StorageAdapterInterface;

final readonly class SideloadSubscriber
{
    public function __construct(
        private StorageConfiguration $config,
        private StorageAdapterInterface $adapter,
        private ?LoggerInterface $logger = null,
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
        $systemTempDir = realpath(sys_get_temp_dir()) ?: sys_get_temp_dir();
        if (!str_starts_with(realpath($tmpName) ?: $tmpName, $systemTempDir)) {
            return;
        }

        // Generate a storage key based on the filename
        $filename = $file['name'] ?? basename($tmpName);
        $key = $this->config->prefix . '/tmp/' . wp_unique_id('sideload_') . '_' . $filename;

        // Upload to storage via adapter
        $contents = @file_get_contents($tmpName);
        if ($contents === false) {
            $this->logger?->warning('Failed to read sideloaded file', ['tmp_name' => $tmpName]);

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
