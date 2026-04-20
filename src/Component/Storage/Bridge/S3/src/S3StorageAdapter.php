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

namespace WPPack\Component\Storage\Bridge\S3;

use AsyncAws\S3\Exception\NoSuchKeyException;
use AsyncAws\S3\Input\CopyObjectRequest;
use AsyncAws\S3\Input\DeleteObjectRequest;
use AsyncAws\S3\Input\DeleteObjectsRequest;
use AsyncAws\S3\Input\GetObjectRequest;
use AsyncAws\S3\Input\HeadObjectRequest;
use AsyncAws\S3\Input\ListObjectsV2Request;
use AsyncAws\S3\Input\PutObjectAclRequest;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use AsyncAws\S3\ValueObject\Delete;
use AsyncAws\S3\ValueObject\ObjectIdentifier;
use WPPack\Component\Storage\Adapter\AbstractStorageAdapter;
use WPPack\Component\Storage\Exception\ObjectNotFoundException;
use WPPack\Component\Storage\ObjectMetadata;
use WPPack\Component\Storage\Visibility;

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

    protected function doWrite(string $path, string $contents, array $metadata = []): void
    {
        $input = [
            'Bucket' => $this->bucket,
            'Key' => $this->prefixPath($path),
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

    protected function doWriteStream(string $path, mixed $resource, array $metadata = []): void
    {
        $input = [
            'Bucket' => $this->bucket,
            'Key' => $this->prefixPath($path),
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

    protected function doRead(string $path): string
    {
        try {
            $result = $this->s3Client->getObject(new GetObjectRequest([
                'Bucket' => $this->bucket,
                'Key' => $this->prefixPath($path),
            ]));

            return $result->getBody()->getContentAsString();
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
            $result = $this->s3Client->getObject(new GetObjectRequest([
                'Bucket' => $this->bucket,
                'Key' => $this->prefixPath($path),
            ]));

            $stream = fopen('php://temp', 'r+');
            \assert($stream !== false);

            foreach ($result->getBody()->getChunks() as $chunk) {
                fwrite($stream, $chunk);
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
        $this->s3Client->deleteObject(new DeleteObjectRequest([
            'Bucket' => $this->bucket,
            'Key' => $this->prefixPath($path),
        ]))->resolve();
    }

    protected function doDeleteMultiple(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $objects = array_map(
            fn(string $path): ObjectIdentifier => new ObjectIdentifier(['Key' => $this->prefixPath($path)]),
            $paths,
        );

        // S3 allows max 1000 objects per request
        foreach (array_chunk($objects, 1000) as $chunk) {
            $this->s3Client->deleteObjects(new DeleteObjectsRequest([
                'Bucket' => $this->bucket,
                'Delete' => new Delete(['Objects' => $chunk]),
            ]))->resolve();
        }
    }

    protected function doFileExists(string $path): bool
    {
        try {
            $this->s3Client->headObject(new HeadObjectRequest([
                'Bucket' => $this->bucket,
                'Key' => $this->prefixPath($path),
            ]))->resolve();

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
        $dirKey = $this->prefixPath($path);

        if (!str_ends_with($dirKey, '/')) {
            $dirKey .= '/';
        }

        $this->s3Client->putObject(new PutObjectRequest([
            'Bucket' => $this->bucket,
            'Key' => $dirKey,
            'Body' => '',
        ]))->resolve();
    }

    protected function doDeleteDirectory(string $path): void
    {
        $prefix = $this->prefixPath($path);

        if (!str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        $result = $this->s3Client->listObjectsV2(new ListObjectsV2Request([
            'Bucket' => $this->bucket,
            'Prefix' => $prefix,
        ]));

        $objects = [];

        foreach ($result as $object) {
            $objectKey = $object->getKey();
            if ($objectKey === null) {
                continue;
            }

            $objects[] = new ObjectIdentifier(['Key' => $objectKey]);

            // S3 allows max 1000 objects per DeleteObjects request
            if (\count($objects) === 1000) {
                $this->s3Client->deleteObjects(new DeleteObjectsRequest([
                    'Bucket' => $this->bucket,
                    'Delete' => new Delete(['Objects' => $objects]),
                ]))->resolve();
                $objects = [];
            }
        }

        if ($objects !== []) {
            $this->s3Client->deleteObjects(new DeleteObjectsRequest([
                'Bucket' => $this->bucket,
                'Delete' => new Delete(['Objects' => $objects]),
            ]))->resolve();
        }
    }

    protected function doDirectoryExists(string $path): bool
    {
        $prefix = $this->prefixPath($path);

        if (!str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        $result = $this->s3Client->listObjectsV2(new ListObjectsV2Request([
            'Bucket' => $this->bucket,
            'Prefix' => $prefix,
            'MaxKeys' => 1,
        ]));

        foreach ($result as $object) {
            return true;
        }

        return false;
    }

    protected function doCopy(string $source, string $destination): void
    {
        $this->s3Client->copyObject(new CopyObjectRequest([
            'Bucket' => $this->bucket,
            'CopySource' => $this->bucket . '/' . $this->prefixPath($source),
            'Key' => $this->prefixPath($destination),
        ]))->resolve();
    }

    protected function doMetadata(string $path): ObjectMetadata
    {
        try {
            $result = $this->s3Client->headObject(new HeadObjectRequest([
                'Bucket' => $this->bucket,
                'Key' => $this->prefixPath($path),
            ]));
            $result->resolve();

            $lastModified = $result->getLastModified();

            return new ObjectMetadata(
                path: $path,
                size: (int) $result->getContentLength(),
                lastModified: $lastModified !== null ? \DateTimeImmutable::createFromInterface($lastModified) : null,
                mimeType: $result->getContentType(),
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

        return sprintf('https://%s.s3.amazonaws.com/%s', $this->bucket, $prefixedPath);
    }

    protected function doTemporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        $input = new GetObjectRequest([
            'Bucket' => $this->bucket,
            'Key' => $this->prefixPath($path),
        ]);

        return $this->s3Client->presign($input, \DateTimeImmutable::createFromInterface($expiration));
    }

    protected function doTemporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
    {
        $input = [
            'Bucket' => $this->bucket,
            'Key' => $this->prefixPath($path),
        ];

        if (isset($options['Content-Type'])) {
            $input['ContentType'] = (string) $options['Content-Type'];
        }

        if (isset($options['Content-Length'])) {
            $input['ContentLength'] = (int) $options['Content-Length'];
        }

        return $this->s3Client->presign(
            new PutObjectRequest($input),
            \DateTimeImmutable::createFromInterface($expiration),
        );
    }

    protected function doSetVisibility(string $path, Visibility $visibility): void
    {
        $acl = match ($visibility) {
            Visibility::PUBLIC => 'public-read',
            Visibility::PRIVATE => 'private',
        };

        $this->s3Client->putObjectAcl(new PutObjectAclRequest([
            'Bucket' => $this->bucket,
            'Key' => $this->prefixPath($path),
            'ACL' => $acl,
        ]))->resolve();
    }

    protected function doListContents(string $path, bool $deep): iterable
    {
        $fullPrefix = $this->prefixPath($path);

        $input = [
            'Bucket' => $this->bucket,
            'Prefix' => $fullPrefix,
        ];

        if (!$deep) {
            $input['Delimiter'] = '/';
        }

        $result = $this->s3Client->listObjectsV2(new ListObjectsV2Request($input));

        // Yield file objects from Contents
        foreach ($result as $object) {
            $objectKey = $object->getKey();
            if ($objectKey === null) {
                continue;
            }

            $strippedPath = $this->stripPath($objectKey);
            $lastModified = $object->getLastModified();

            yield new ObjectMetadata(
                path: $strippedPath,
                size: (int) $object->getSize(),
                lastModified: $lastModified !== null ? \DateTimeImmutable::createFromInterface($lastModified) : null,
            );
        }

        // Yield directory entries from CommonPrefixes (only in non-deep mode)
        if (!$deep) {
            foreach ($result->getCommonPrefixes() as $commonPrefix) {
                $prefixValue = $commonPrefix->getPrefix();
                if ($prefixValue === null) {
                    continue;
                }

                $dirPath = $this->stripPath($prefixValue);

                yield new ObjectMetadata(
                    path: rtrim($dirPath, '/') . '/',
                    isDirectory: true,
                );
            }
        }
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
        return $e instanceof NoSuchKeyException;
    }
}
