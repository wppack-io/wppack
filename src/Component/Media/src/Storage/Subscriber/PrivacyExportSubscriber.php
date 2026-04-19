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

/**
 * Handles privacy export file operations for object storage.
 *
 * ZipArchive does not work with stream wrappers (e.g. s3://), so during export
 * file generation, wp_privacy_exports_dir is temporarily redirected to a local
 * temp directory. After export, the file is uploaded to storage and cleaned up.
 *
 * Not readonly because it holds a filter callback reference that must be added/removed.
 */
final class PrivacyExportSubscriber
{
    /** @var \Closure(string): string|null */
    private ?\Closure $exportsFilter = null;

    private ?string $localTempDir = null;

    public function __construct(
        private readonly StorageConfiguration $config,
        private readonly StorageAdapterInterface $adapter,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Before WordPress writes the export file, redirect wp_privacy_exports_dir to local temp.
     */
    #[AsEventListener(event: 'wp_privacy_personal_data_export_file', priority: 5)]
    public function onBeforeExport(WordPressEvent $event): void
    {
        $this->localTempDir = sys_get_temp_dir() . '/wppack_privacy_exports_' . wp_unique_id();

        if (!is_dir($this->localTempDir)) {
            mkdir($this->localTempDir, 0700, true);
        }

        $localDir = $this->localTempDir;
        $this->exportsFilter = static function (string $dir) use ($localDir): string {
            return $localDir . '/';
        };

        add_filter('wp_privacy_exports_dir', $this->exportsFilter, 1);
    }

    /**
     * After WordPress writes the export file, remove the temporary directory redirect.
     */
    #[AsEventListener(event: 'wp_privacy_personal_data_export_file', priority: 20)]
    public function onAfterExport(WordPressEvent $event): void
    {
        if ($this->exportsFilter !== null) {
            remove_filter('wp_privacy_exports_dir', $this->exportsFilter, 1);
            $this->exportsFilter = null;
        }
    }

    /**
     * After the export file is created, upload it to storage and clean up local temp.
     */
    #[AsEventListener(event: 'wp_privacy_personal_data_export_file_created', priority: 20)]
    public function onExportFileCreated(WordPressEvent $event): void
    {
        // Defensive: ensure filter is removed even if onAfterExport was skipped
        if ($this->exportsFilter !== null) {
            remove_filter('wp_privacy_exports_dir', $this->exportsFilter, 1);
            $this->exportsFilter = null;
        }

        if ($this->localTempDir === null || !is_dir($this->localTempDir)) {
            return;
        }

        // Find and upload all files in the temp directory
        $files = glob($this->localTempDir . '/*');
        if ($files === false) {
            $files = [];
        }

        // Get the exports directory relative path from WordPress
        $exportsDir = wp_privacy_exports_dir();
        $uploadsDir = wp_upload_dir();
        $basedir = $uploadsDir['basedir'] ?? '';

        foreach ($files as $localFile) {
            if (!is_file($localFile)) {
                continue;
            }

            $filename = basename($localFile);

            // Determine relative path within uploads using directory comparison
            if ($basedir !== '' && str_starts_with($exportsDir, $basedir)) {
                $relativePath = ltrim(substr($exportsDir, \strlen($basedir)), '/');
            } else {
                $relativePath = 'wp-personal-data-exports/';
            }

            $key = $this->config->prefix . '/' . $relativePath . $filename;

            $contents = @file_get_contents($localFile);
            if ($contents !== false) {
                $this->adapter->write($key, $contents);
            } else {
                $this->logger?->warning('Failed to read privacy export file', ['file' => $localFile]);
            }

            if (!unlink($localFile)) {
                $this->logger?->warning('Failed to delete local privacy export file', ['file' => $localFile]);
            }
        }

        if (!rmdir($this->localTempDir)) {
            $this->logger?->warning('Failed to remove local privacy export temp directory', ['dir' => $this->localTempDir]);
        }
        $this->localTempDir = null;
    }
}
