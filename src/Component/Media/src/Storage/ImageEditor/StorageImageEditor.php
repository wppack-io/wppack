<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Storage\ImageEditor;

/**
 * Image editor that handles images stored in remote object storage.
 *
 * Downloads stream wrapper paths to local temporary files for Imagick processing,
 * then saves processed images back through the stream wrapper.
 */
class StorageImageEditor extends \WP_Image_Editor_Imagick
{
    /** @var list<string> */
    private array $tempFiles = [];

    /**
     * Load the image from a stream wrapper path.
     *
     * Downloads the remote file to a local temporary file before loading
     * with Imagick, since Imagick cannot directly read stream wrappers.
     *
     * @return \WP_Error|true
     */
    public function load(): \WP_Error|true
    {
        $file = $this->file;

        // If the file is a stream wrapper path, download to a local temp file
        if (str_contains($file, '://') && !str_starts_with($file, 'file://')) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                return new \WP_Error(
                    'image_editor_load_error',
                    sprintf('Failed to read file from storage: %s', $file),
                );
            }

            $extension = pathinfo($file, \PATHINFO_EXTENSION);
            $tempFile = wp_tempnam('storage_image_' . ($extension !== '' ? '.' . $extension : ''));

            if (file_put_contents($tempFile, $contents) === false) {
                return new \WP_Error(
                    'image_editor_load_error',
                    sprintf('Failed to write temporary file: %s', $tempFile),
                );
            }

            $this->tempFiles[] = $tempFile;
            $this->file = $tempFile;
        }

        return parent::load();
    }

    /**
     * Save the processed image, supporting stream wrapper destinations.
     *
     * @param \Imagick    $image
     * @param string|null $filename
     * @param string|null $mimeType
     *
     * @return \WP_Error|array{path: string, file: string, width: int, height: int, mime-type: string}
     */
    public function _save($image, $filename = null, $mimeType = null)
    {
        $isStreamWrapper = $filename !== null
            && str_contains($filename, '://')
            && !str_starts_with($filename, 'file://');

        $localFilename = $filename;

        if ($isStreamWrapper) {
            // Save to a temp file first, then copy to stream wrapper
            $extension = pathinfo($filename, \PATHINFO_EXTENSION);
            $localFilename = wp_tempnam('storage_save_' . ($extension !== '' ? '.' . $extension : ''));
            $this->tempFiles[] = $localFilename;
        }

        $result = parent::_save($image, $localFilename, $mimeType);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        if ($isStreamWrapper) {
            $localPath = $result['path'];
            $contents = file_get_contents($localPath);

            if ($contents === false) {
                return new \WP_Error(
                    'image_editor_save_error',
                    sprintf('Failed to read processed image: %s', $localPath),
                );
            }

            // Determine the actual stream wrapper destination path
            $destDir = \dirname($filename);
            $destPath = $destDir . '/' . $result['file'];

            if (file_put_contents($destPath, $contents) === false) {
                return new \WP_Error(
                    'image_editor_save_error',
                    sprintf('Failed to write to storage: %s', $destPath),
                );
            }

            $result['path'] = $destPath;
        }

        return $result;
    }

    /**
     * Clean up temporary files on destruction.
     */
    public function __destruct()
    {
        foreach ($this->tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $this->tempFiles = [];

        parent::__destruct();
    }
}
