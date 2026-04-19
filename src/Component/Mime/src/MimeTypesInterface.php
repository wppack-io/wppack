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

namespace WPPack\Component\Mime;

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
     * Uses get_allowed_mime_types() with upload_mimes filter applied.
     *
     * @return array<string, string> ext_pattern => mime_type
     */
    public function getAllowedMimeTypes(?int $userId = null): array;

    /**
     * Returns the file type category for an extension (image, audio, video, document, etc.).
     *
     * Uses wp_ext2type().
     */
    public function getExtensionType(string $extension): ?string;

    /**
     * Validates a file's MIME type and extension from its content and filename.
     *
     * Uses wp_check_filetype_and_ext() with security verification.
     */
    public function validateFile(string $filePath, string $filename): FileTypeInfo;

    /**
     * Sanitizes a MIME type string.
     *
     * Uses sanitize_mime_type().
     */
    public function sanitize(string $mimeType): string;
}
