<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Bridge\Azure;

use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use WpPack\Component\Storage\Adapter\AbstractStorageAdapter;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\ObjectMetadata;

final class AzureStorageAdapter extends AbstractStorageAdapter
{
    public function __construct(
        private readonly BlobServiceClient $serviceClient,
        private readonly string $container,
        private readonly string $prefix = '',
        private readonly ?string $publicUrl = null,
    ) {}

    public function getName(): string
    {
        return 'azure';
    }

    protected function doWrite(string $key, string $contents, array $metadata = []): void
    {
        $options = [];

        if (isset($metadata['Content-Type'])) {
            $options['contentType'] = $metadata['Content-Type'];
            unset($metadata['Content-Type']);
        }

        if ($metadata !== []) {
            $options['metadata'] = $metadata;
        }

        $this->getBlobClient($key)->upload($contents, $options);
    }

    protected function doWriteStream(string $key, mixed $resource, array $metadata = []): void
    {
        $options = [];

        if (isset($metadata['Content-Type'])) {
            $options['contentType'] = $metadata['Content-Type'];
            unset($metadata['Content-Type']);
        }

        if ($metadata !== []) {
            $options['metadata'] = $metadata;
        }

        $this->getBlobClient($key)->upload($resource, $options);
    }

    protected function doRead(string $key): string
    {
        try {
            return $this->getBlobClient($key)->downloadStreaming()->getBody()->getContents();
        } catch (\Throwable $e) {
            if ($this->isNotFoundException($e)) {
                throw new ObjectNotFoundException($key, $e);
            }
            throw $e;
        }
    }

    protected function doReadStream(string $key): mixed
    {
        try {
            $body = $this->getBlobClient($key)->downloadStreaming()->getBody();

            $stream = fopen('php://temp', 'r+');
            \assert($stream !== false);
            fwrite($stream, $body->getContents());
            rewind($stream);

            return $stream;
        } catch (\Throwable $e) {
            if ($this->isNotFoundException($e)) {
                throw new ObjectNotFoundException($key, $e);
            }
            throw $e;
        }
    }

    protected function doDelete(string $key): void
    {
        $this->getBlobClient($key)->delete();
    }

    protected function doExists(string $key): bool
    {
        try {
            $this->getBlobClient($key)->getProperties();

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
        $sourceClient = $this->getBlobClient($sourceKey);
        $destinationClient = $this->getBlobClient($destinationKey);

        $destinationClient->copyFromUrl($sourceClient->uri);
    }

    protected function doMetadata(string $key): ObjectMetadata
    {
        try {
            $properties = $this->getBlobClient($key)->getProperties();

            return new ObjectMetadata(
                key: $key,
                size: $properties->contentLength,
                lastModified: $properties->lastModified !== null
                    ? \DateTimeImmutable::createFromInterface($properties->lastModified)
                    : null,
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

        return (string) $this->getBlobClient($key)->uri;
    }

    protected function doTemporaryUrl(string $key, \DateTimeInterface $expiration): string
    {
        $blobClient = $this->getBlobClient($key);
        $sasBuilder = BlobSasBuilder::new($this->container, $this->prefixKey($key))
            ->setExpiresOn(\DateTimeImmutable::createFromInterface($expiration))
            ->setPermissions('r');

        return (string) $blobClient->generateSasUri($sasBuilder);
    }

    protected function doListContents(string $prefix, bool $recursive): iterable
    {
        $fullPrefix = $this->prefixKey($prefix);

        $options = ['prefix' => $fullPrefix];

        if (!$recursive) {
            $options['delimiter'] = '/';
        }

        $containerClient = $this->serviceClient->getContainerClient($this->container);

        foreach ($containerClient->getBlobsByHierarchy($options) as $blob) {
            $blobKey = $this->stripPrefix($blob->name);

            yield new ObjectMetadata(
                key: $blobKey,
                size: $blob->contentLength,
                lastModified: $blob->lastModified !== null
                    ? \DateTimeImmutable::createFromInterface($blob->lastModified)
                    : null,
            );
        }
    }

    private function getBlobClient(string $key): BlobClient
    {
        return $this->serviceClient
            ->getContainerClient($this->container)
            ->getBlobClient($this->prefixKey($key));
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
        $message = $e->getMessage();

        return str_contains($message, '404')
            || str_contains($message, 'Not Found')
            || str_contains($message, 'BlobNotFound');
    }
}
