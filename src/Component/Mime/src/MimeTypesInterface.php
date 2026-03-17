<?php

declare(strict_types=1);

namespace WpPack\Component\Mime;

interface MimeTypesInterface extends MimeTypeGuesserInterface
{
    /**
     * @return list<string> Extensions without leading dot
     */
    public function getExtensions(string $mimeType): array;

    /**
     * @return list<string> MIME types
     */
    public function getMimeTypes(string $extension): array;

    /**
     * Returns MIME types allowed for upload.
     *
     * WP: get_allowed_mime_types() with upload_mimes filter applied.
     * Non-WP: all MIME types (no restriction).
     *
     * @return array<string, string> ext_pattern => mime_type
     */
    public function getAllowedMimeTypes(?int $userId = null): array;

    /**
     * Returns the file type category for an extension (image, audio, video, document, etc.).
     *
     * WP: uses wp_ext2type().
     * Non-WP: static map fallback.
     */
    public function getExtensionType(string $extension): ?string;

    /**
     * Validates a file's MIME type and extension from its content and filename.
     *
     * WP: uses wp_check_filetype_and_ext() with security verification.
     * Non-WP: finfo + extension matching fallback.
     */
    public function validateFile(string $filePath, string $filename): FileTypeInfo;

    /**
     * Sanitizes a MIME type string.
     *
     * WP: uses sanitize_mime_type().
     * Non-WP: regex-based invalid character removal.
     */
    public function sanitize(string $mimeType): string;
}
