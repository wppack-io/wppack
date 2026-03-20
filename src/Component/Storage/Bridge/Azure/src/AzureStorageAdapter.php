<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Bridge\Azure;

use AzureOss\Storage\Blob\Models\Blob;
use AzureOss\Storage\Blob\Models\BlobPrefix;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use AzureOss\Storage\Blob\Exceptions\BlobNotFoundException;
use AzureOss\Storage\Blob\Models\BlobHttpHeaders;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use WpPack\Component\Storage\Adapter\AbstractStorageAdapter;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\ObjectMetadata;

final class AzureStorageAdapter extends AbstractStorageAdapter
{
    public function __construct(
        private readonly AzureBlobClientInterface $client,
        private readonly string $prefix = '',
        private readonly ?string $publicUrl = null,
    ) {}

    public function getName(): string
    {
        return 'azure';
    }

    protected function doWrite(string $path, string $contents, array $metadata = []): void
    {
        $options = $this->buildUploadOptions($metadata);

        $this->client->upload($this->prefixPath($path), $contents, $options);
    }

    protected function doWriteStream(string $path, mixed $resource, array $metadata = []): void
    {
        $options = $this->buildUploadOptions($metadata);

        $this->client->upload($this->prefixPath($path), $resource, $options);
    }

    protected function doRead(string $path): string
    {
        try {
            return $this->client->downloadStreamingContent($this->prefixPath($path))->getContents();
        } catch (\Throwable $e) {
            if ($this->isNotFoundException($e)) {
                throw new ObjectNotFoundException($path, $e);
            }
            throw $e;
        }
    }

    protected function doReadStream(string $path): mixed
    {
        $stream = null;

        try {
            $body = $this->client->downloadStreamingContent($this->prefixPath($path));

            $stream = fopen('php://temp', 'r+');
            \assert($stream !== false);

            while (!$body->eof()) {
                fwrite($stream, $body->read(8192));
            }

            rewind($stream);

            $result = $stream;
            $stream = null;

            return $result;
        } catch (\Throwable $e) {
            if (\is_resource($stream)) {
                fclose($stream);
            }

            if ($this->isNotFoundException($e)) {
                throw new ObjectNotFoundException($path, $e);
            }
            throw $e;
        }
    }

    protected function doDelete(string $path): void
    {
        $this->client->delete($this->prefixPath($path));
    }

    protected function doFileExists(string $path): bool
    {
        try {
            $this->client->getProperties($this->prefixPath($path));

            return true;
        } catch (\Throwable $e) {
            if ($this->isNotFoundException($e)) {
                return false;
            }
            throw $e;
        }
    }

    protected function doCreateDirectory(string $path): void
    {
        $dirPath = rtrim($this->prefixPath($path), '/') . '/';
        $this->client->upload($dirPath, '', null);
    }

    protected function doDeleteDirectory(string $path): void
    {
        $dirPrefix = rtrim($this->prefixPath($path), '/') . '/';
        $blobs = $this->client->listBlobsByHierarchy($dirPrefix, '');

        foreach ($blobs as $blob) {
            if ($blob instanceof Blob) {
                $this->client->delete($blob->name);
            }
        }
    }

    protected function doDirectoryExists(string $path): bool
    {
        $dirPrefix = rtrim($this->prefixPath($path), '/') . '/';
        $blobs = $this->client->listBlobsByHierarchy($dirPrefix, '');

        foreach ($blobs as $_) {
            return true;
        }

        return false;
    }

    protected function doCopy(string $source, string $destination): void
    {
        $sourceUri = $this->client->getBlobUri($this->prefixPath($source));
        $this->client->syncCopyFromUri($this->prefixPath($destination), $sourceUri);
    }

    protected function doMetadata(string $path): ObjectMetadata
    {
        try {
            $properties = $this->client->getProperties($this->prefixPath($path));

            return new ObjectMetadata(
                path: $path,
                size: $properties->contentLength,
                lastModified: \DateTimeImmutable::createFromInterface($properties->lastModified),
                mimeType: $properties->contentType,
            );
        } catch (\Throwable $e) {
            if ($this->isNotFoundException($e)) {
                throw new ObjectNotFoundException($path, $e);
            }
            throw $e;
        }
    }

    protected function doPublicUrl(string $path): string
    {
        $prefixedPath = $this->prefixPath($path);

        if ($this->publicUrl !== null) {
            return rtrim($this->publicUrl, '/') . '/' . ltrim($prefixedPath, '/');
        }

        return (string) $this->client->getBlobUri($prefixedPath);
    }

    protected function doTemporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        $prefixedPath = $this->prefixPath($path);
        $sasBuilder = BlobSasBuilder::new()
            ->setExpiresOn(\DateTimeImmutable::createFromInterface($expiration))
            ->setPermissions('r');

        return (string) $this->client->generateSasUri($prefixedPath, $sasBuilder);
    }

    protected function doListContents(string $path, bool $deep): iterable
    {
        $fullPrefix = $this->prefixPath($path);
        $prefixArg = $fullPrefix !== '' ? $fullPrefix : null;

        $blobs = $deep
            ? $this->client->listBlobsByHierarchy($prefixArg, '')
            : $this->client->listBlobsByHierarchy($prefixArg, '/');

        foreach ($blobs as $blob) {
            if ($blob instanceof BlobPrefix) {
                $dirPath = $this->stripPath($blob->name);

                yield new ObjectMetadata(
                    path: $dirPath,
                    isDirectory: true,
                );

                continue;
            }

            /** @var Blob $blob */
            $blobPath = $this->stripPath($blob->name);

            yield new ObjectMetadata(
                path: $blobPath,
                size: $blob->properties->contentLength,
                lastModified: \DateTimeImmutable::createFromInterface($blob->properties->lastModified),
                mimeType: $blob->properties->contentType,
            );
        }
    }

    /**
     * @param array<string, string> $metadata
     */
    private function buildUploadOptions(array $metadata): ?UploadBlobOptions
    {
        $contentType = $metadata['Content-Type'] ?? null;

        $headerMap = [
            'Cache-Control' => 'cacheControl',
            'Content-Disposition' => 'contentDisposition',
            'Content-Encoding' => 'contentEncoding',
            'Content-Language' => 'contentLanguage',
        ];

        $headerArgs = [];
        foreach ($headerMap as $metaKey => $headerProp) {
            if (isset($metadata[$metaKey])) {
                $headerArgs[$headerProp] = $metadata[$metaKey];
            }
        }

        if ($contentType === null && $headerArgs === []) {
            return null;
        }

        return new UploadBlobOptions(
            contentType: $contentType,
            httpHeaders: $headerArgs !== [] ? new BlobHttpHeaders(...$headerArgs) : null,
        );
    }

    private function prefixPath(string $path): string
    {
        if ($this->prefix === '') {
            return $path;
        }

        return rtrim($this->prefix, '/') . '/' . ltrim($path, '/');
    }

    private function stripPath(string $path): string
    {
        if ($this->prefix === '') {
            return $path;
        }

        $prefix = rtrim($this->prefix, '/') . '/';

        if (str_starts_with($path, $prefix)) {
            return substr($path, \strlen($prefix));
        }

        return $path;
    }

    private function isNotFoundException(\Throwable $e): bool
    {
        return $e instanceof BlobNotFoundException;
    }
}
