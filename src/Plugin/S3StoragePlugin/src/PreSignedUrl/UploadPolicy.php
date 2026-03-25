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

namespace WpPack\Plugin\S3StoragePlugin\PreSignedUrl;

use WpPack\Component\Mime\MimeTypes;
use WpPack\Component\Mime\MimeTypesInterface;

final readonly class UploadPolicy
{
    private const DEFAULT_MAX_FILE_SIZE = 100 * 1024 * 1024; // 100 MB

    /** @var list<string> */
    private array $allowedMimeTypes;
    private int $maxFileSize;
    private MimeTypesInterface $mimeTypes;

    /**
     * @param list<string>|null $allowedMimeTypes
     */
    public function __construct(
        ?int $maxFileSize = null,
        ?array $allowedMimeTypes = null,
        ?MimeTypesInterface $mimeTypes = null,
    ) {
        $this->mimeTypes = $mimeTypes ?? MimeTypes::getDefault();
        $this->maxFileSize = $maxFileSize ?? self::DEFAULT_MAX_FILE_SIZE;
        $this->allowedMimeTypes = $allowedMimeTypes ?? $this->resolveDefaultMimeTypes();
    }

    public function isAllowedType(string $contentType): bool
    {
        if ($this->allowedMimeTypes === []) {
            return true;
        }

        return \in_array($contentType, $this->allowedMimeTypes, true);
    }

    public function isAllowedSize(int $contentLength): bool
    {
        return $contentLength > 0 && $contentLength <= $this->maxFileSize;
    }

    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /**
     * @return list<string>
     */
    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    /**
     * @return list<string>
     */
    private function resolveDefaultMimeTypes(): array
    {
        $allowed = $this->mimeTypes->getAllowedMimeTypes();

        return array_values(array_unique($allowed));
    }
}
