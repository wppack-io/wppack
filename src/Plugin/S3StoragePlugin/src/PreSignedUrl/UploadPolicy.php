<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\PreSignedUrl;

use WpPack\Component\Mime\MimeTypes;
use WpPack\Component\Mime\MimeTypesInterface;

final class UploadPolicy
{
    private const DEFAULT_MAX_FILE_SIZE = 100 * 1024 * 1024; // 100 MB

    /** @var list<string> */
    private readonly array $allowedMimeTypes;
    private readonly int $maxFileSize;

    /**
     * @param list<string>|null $allowedMimeTypes
     */
    public function __construct(
        ?int $maxFileSize = null,
        ?array $allowedMimeTypes = null,
        ?MimeTypesInterface $mimeTypes = null,
    ) {
        $this->maxFileSize = $maxFileSize ?? self::DEFAULT_MAX_FILE_SIZE;
        $this->allowedMimeTypes = $allowedMimeTypes ?? $this->resolveDefaultMimeTypes($mimeTypes ?? MimeTypes::getDefault());
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
    private function resolveDefaultMimeTypes(MimeTypesInterface $mimeTypes): array
    {
        $allowed = $mimeTypes->getAllowedMimeTypes();

        return array_values(array_unique($allowed));
    }
}
