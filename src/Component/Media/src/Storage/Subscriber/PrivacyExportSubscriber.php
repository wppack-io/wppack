<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Storage\Subscriber;

use WpPack\Component\EventDispatcher\Attribute\AsEventListener;
use WpPack\Component\EventDispatcher\WordPressEvent;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;

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

            // Build the storage key based on the exports URL path
            $exportsUrl = wp_privacy_exports_url();
            $baseUrl = $uploadsDir['baseurl'] ?? '';

            // Determine relative path within uploads
            $relativePath = '';
            if ($baseUrl !== '' && str_starts_with($exportsUrl, $baseUrl)) {
                $relativePath = ltrim(substr($exportsUrl, \strlen($baseUrl)), '/');
            } else {
                $relativePath = 'wp-personal-data-exports/';
            }

            $key = $this->config->prefix . '/' . $relativePath . $filename;

            $contents = file_get_contents($localFile);
            if ($contents !== false) {
                $this->adapter->write($key, $contents);
            }

            @unlink($localFile);
        }

        @rmdir($this->localTempDir);
        $this->localTempDir = null;
    }
}
