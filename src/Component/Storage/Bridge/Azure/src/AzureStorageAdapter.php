<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Bridge\Azure;

use AzureOss\Storage\Blob\Models\Blob;
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

    protected function doWrite(string $key, string $contents, array $metadata = []): void
    {
        $options = $this->buildUploadOptions($metadata);

        $this->client->upload($this->prefixKey($key), $contents, $options);
    }

    protected function doWriteStream(string $key, mixed $resource, array $metadata = []): void
    {
        $options = $this->buildUploadOptions($metadata);

        $this->client->upload($this->prefixKey($key), $resource, $options);
    }

    protected function doRead(string $key): string
    {
        try {
            return $this->client->downloadStreamingContent($this->prefixKey($key))->getContents();
        } catch (\Throwable $e) {
            if ($this->isNotFoundException($e)) {
                throw new ObjectNotFoundException($key, $e);
            }
            throw $e;
        }
    }

    protected function doReadStream(string $key): mixed
    {
        $stream = null;

        try {
            $body = $this->client->downloadStreamingContent($this->prefixKey($key));

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
                throw new ObjectNotFoundException($key, $e);
            }
            throw $e;
        }
    }

    protected function doDelete(string $key): void
    {
        $this->client->delete($this->prefixKey($key));
    }

    protected function doExists(string $key): bool
    {
        try {
            $this->client->getProperties($this->prefixKey($key));

            return true;
        } catch (\Throwable $e) {
            if ($this->isNotFoundException($e)) {
                return false;
            }
            throw $e;
        }
    }

    protected function doCopy(string $sourceKey, string $destinationKey): void
    {
        $sourceUri = $this->client->getBlobUri($this->prefixKey($sourceKey));
        $this->client->syncCopyFromUri($this->prefixKey($destinationKey), $sourceUri);
    }

    protected function doMetadata(string $key): ObjectMetadata
    {
        try {
            $properties = $this->client->getProperties($this->prefixKey($key));

            return new ObjectMetadata(
                key: $key,
                size: $properties->contentLength,
                lastModified: \DateTimeImmutable::createFromInterface($properties->lastModified),
                mimeType: $properties->contentType,
            );
        } catch (\Throwable $e) {
            if ($this->isNotFoundException($e)) {
                throw new ObjectNotFoundException($key, $e);
            }
            throw $e;
        }
    }

    protected function doPublicUrl(string $key): string
    {
        $prefixedKey = $this->prefixKey($key);

        if ($this->publicUrl !== null) {
            return rtrim($this->publicUrl, '/') . '/' . ltrim($prefixedKey, '/');
        }

        return (string) $this->client->getBlobUri($prefixedKey);
    }

    protected function doTemporaryUrl(string $key, \DateTimeInterface $expiration): string
    {
        $prefixedKey = $this->prefixKey($key);
        $sasBuilder = BlobSasBuilder::new()
            ->setExpiresOn(\DateTimeImmutable::createFromInterface($expiration))
            ->setPermissions('r');

        return (string) $this->client->generateSasUri($prefixedKey, $sasBuilder);
    }

    protected function doListContents(string $prefix, bool $recursive): iterable
    {
        $fullPrefix = $this->prefixKey($prefix);
        $prefixArg = $fullPrefix !== '' ? $fullPrefix : null;

        $blobs = $recursive
            ? $this->client->listBlobsByHierarchy($prefixArg, '')
            : $this->client->listBlobsByHierarchy($prefixArg, '/');

        foreach ($blobs as $blob) {
            if (!$blob instanceof Blob) {
                continue;
            }

            $blobKey = $this->stripPrefix($blob->name);

            yield new ObjectMetadata(
                key: $blobKey,
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

    private function prefixKey(string $key): string
    {
        if ($this->prefix === '') {
            return $key;
        }

        return rtrim($this->prefix, '/') . '/' . ltrim($key, '/');
    }

    private function stripPrefix(string $key): string
    {
        if ($this->prefix === '') {
            return $key;
        }

        $prefix = rtrim($this->prefix, '/') . '/';

        if (str_starts_with($key, $prefix)) {
            return substr($key, \strlen($prefix));
        }

        return $key;
    }

    private function isNotFoundException(\Throwable $e): bool
    {
        return $e instanceof BlobNotFoundException;
    }
}
