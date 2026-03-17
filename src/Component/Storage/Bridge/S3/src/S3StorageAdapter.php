<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Bridge\S3;

use AsyncAws\S3\Input\CopyObjectRequest;
use AsyncAws\S3\Input\DeleteObjectRequest;
use AsyncAws\S3\Input\DeleteObjectsRequest;
use AsyncAws\S3\Input\GetObjectRequest;
use AsyncAws\S3\Input\HeadObjectRequest;
use AsyncAws\S3\Input\ListObjectsV2Request;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use AsyncAws\S3\ValueObject\Delete;
use AsyncAws\S3\ValueObject\ObjectIdentifier;
use WpPack\Component\Storage\Adapter\AbstractStorageAdapter;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\ObjectMetadata;

final class S3StorageAdapter extends AbstractStorageAdapter
{
    public function __construct(
        private readonly S3Client $s3Client,
        private readonly string $bucket,
        private readonly string $prefix = '',
        private readonly ?string $publicUrl = null,
    ) {}

    public function getName(): string
    {
        return 's3';
    }

    protected function doWrite(string $key, string $contents, array $metadata = []): void
    {
        $input = [
            'Bucket' => $this->bucket,
            'Key' => $this->prefixKey($key),
            'Body' => $contents,
        ];

        if (isset($metadata['Content-Type'])) {
            $input['ContentType'] = $metadata['Content-Type'];
            unset($metadata['Content-Type']);
        }

        if ($metadata !== []) {
            $input['Metadata'] = $metadata;
        }

        $this->s3Client->putObject(new PutObjectRequest($input))->resolve();
    }

    protected function doWriteStream(string $key, mixed $resource, array $metadata = []): void
    {
        $input = [
            'Bucket' => $this->bucket,
            'Key' => $this->prefixKey($key),
            'Body' => $resource,
        ];

        if (isset($metadata['Content-Type'])) {
            $input['ContentType'] = $metadata['Content-Type'];
            unset($metadata['Content-Type']);
        }

        if ($metadata !== []) {
            $input['Metadata'] = $metadata;
        }

        $this->s3Client->putObject(new PutObjectRequest($input))->resolve();
    }

    protected function doRead(string $key): string
    {
        try {
            $result = $this->s3Client->getObject(new GetObjectRequest([
                'Bucket' => $this->bucket,
                'Key' => $this->prefixKey($key),
            ]));

            return $result->getBody()->getContentAsString();
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
            $result = $this->s3Client->getObject(new GetObjectRequest([
                'Bucket' => $this->bucket,
                'Key' => $this->prefixKey($key),
            ]));

            $stream = fopen('php://temp', 'r+');
            \assert($stream !== false);
            fwrite($stream, $result->getBody()->getContentAsString());
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
        $this->s3Client->deleteObject(new DeleteObjectRequest([
            'Bucket' => $this->bucket,
            'Key' => $this->prefixKey($key),
        ]))->resolve();
    }

    protected function doDeleteMultiple(array $keys): void
    {
        if ($keys === []) {
            return;
        }

        $objects = array_map(
            fn(string $key): ObjectIdentifier => new ObjectIdentifier(['Key' => $this->prefixKey($key)]),
            $keys,
        );

        // S3 allows max 1000 objects per request
        foreach (array_chunk($objects, 1000) as $chunk) {
            $this->s3Client->deleteObjects(new DeleteObjectsRequest([
                'Bucket' => $this->bucket,
                'Delete' => new Delete(['Objects' => $chunk]),
            ]))->resolve();
        }
    }

    protected function doExists(string $key): bool
    {
        try {
            $this->s3Client->headObject(new HeadObjectRequest([
                'Bucket' => $this->bucket,
                'Key' => $this->prefixKey($key),
            ]))->resolve();

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
        $this->s3Client->copyObject(new CopyObjectRequest([
            'Bucket' => $this->bucket,
            'CopySource' => $this->bucket . '/' . $this->prefixKey($sourceKey),
            'Key' => $this->prefixKey($destinationKey),
        ]))->resolve();
    }

    protected function doMetadata(string $key): ObjectMetadata
    {
        try {
            $result = $this->s3Client->headObject(new HeadObjectRequest([
                'Bucket' => $this->bucket,
                'Key' => $this->prefixKey($key),
            ]));
            $result->resolve();

            $lastModified = $result->getLastModified();

            return new ObjectMetadata(
                key: $key,
                size: (int) $result->getContentLength(),
                lastModified: $lastModified !== null ? \DateTimeImmutable::createFromInterface($lastModified) : null,
                mimeType: $result->getContentType(),
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

        return sprintf('https://%s.s3.amazonaws.com/%s', $this->bucket, $prefixedKey);
    }

    protected function doTemporaryUrl(string $key, \DateTimeInterface $expiration): string
    {
        $input = new GetObjectRequest([
            'Bucket' => $this->bucket,
            'Key' => $this->prefixKey($key),
        ]);

        $now = new \DateTimeImmutable();
        $interval = $now->diff(\DateTimeImmutable::createFromInterface($expiration));
        $seconds = $interval->days * 86400 + $interval->h * 3600 + $interval->i * 60 + $interval->s;

        if ($seconds <= 0) {
            $seconds = 3600;
        }

        return $this->s3Client->presign($input, \DateTimeImmutable::createFromInterface($expiration));
    }

    protected function doListContents(string $prefix, bool $recursive): iterable
    {
        $fullPrefix = $this->prefixKey($prefix);

        $input = [
            'Bucket' => $this->bucket,
            'Prefix' => $fullPrefix,
        ];

        if (!$recursive) {
            $input['Delimiter'] = '/';
        }

        $result = $this->s3Client->listObjectsV2(new ListObjectsV2Request($input));

        foreach ($result as $object) {
            $objectKey = $object->getKey();
            if ($objectKey === null) {
                continue;
            }

            $key = $this->stripPrefix($objectKey);
            $lastModified = $object->getLastModified();

            yield new ObjectMetadata(
                key: $key,
                size: (int) $object->getSize(),
                lastModified: $lastModified !== null ? \DateTimeImmutable::createFromInterface($lastModified) : null,
            );
        }
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
            || str_contains($message, 'NoSuchKey');
    }
}
