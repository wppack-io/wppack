<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Bridge\Gcs;

use Google\Cloud\Storage\Bucket;
use WpPack\Component\Storage\Adapter\AbstractStorageAdapter;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\ObjectMetadata;

final class GcsStorageAdapter extends AbstractStorageAdapter
{
    public function __construct(
        private readonly Bucket $bucket,
        private readonly string $prefix = '',
        private readonly ?string $publicUrl = null,
    ) {}

    public function getName(): string
    {
        return 'gcs';
    }

    protected function doWrite(string $key, string $contents, array $metadata = []): void
    {
        $options = [
            'name' => $this->prefixKey($key),
        ];

        if (isset($metadata['Content-Type'])) {
            $options['metadata'] = ['contentType' => $metadata['Content-Type']];
            unset($metadata['Content-Type']);
        }

        if ($metadata !== []) {
            $options['metadata'] = array_merge($options['metadata'] ?? [], ['metadata' => $metadata]);
        }

        $this->bucket->upload($contents, $options);
    }

    protected function doWriteStream(string $key, mixed $resource, array $metadata = []): void
    {
        $options = [
            'name' => $this->prefixKey($key),
        ];

        if (isset($metadata['Content-Type'])) {
            $options['metadata'] = ['contentType' => $metadata['Content-Type']];
            unset($metadata['Content-Type']);
        }

        if ($metadata !== []) {
            $options['metadata'] = array_merge($options['metadata'] ?? [], ['metadata' => $metadata]);
        }

        $this->bucket->upload($resource, $options);
    }

    protected function doRead(string $key): string
    {
        $object = $this->bucket->object($this->prefixKey($key));

        try {
            return $object->downloadAsString();
        } catch (\Throwable $e) {
            if ($this->isNotFoundException($e)) {
                throw new ObjectNotFoundException($key, $e);
            }
            throw $e;
        }
    }

    protected function doReadStream(string $key): mixed
    {
        $object = $this->bucket->object($this->prefixKey($key));

        try {
            $body = $object->downloadAsStream();

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
        $this->bucket->object($this->prefixKey($key))->delete();
    }

    protected function doExists(string $key): bool
    {
        return $this->bucket->object($this->prefixKey($key))->exists();
    }

    protected function doCopy(string $sourceKey, string $destinationKey): void
    {
        $this->bucket->object($this->prefixKey($sourceKey))->copy($this->bucket->name(), [
            'name' => $this->prefixKey($destinationKey),
        ]);
    }

    protected function doMetadata(string $key): ObjectMetadata
    {
        $object = $this->bucket->object($this->prefixKey($key));

        try {
            $info = $object->info();
        } catch (\Throwable $e) {
            if ($this->isNotFoundException($e)) {
                throw new ObjectNotFoundException($key, $e);
            }
            throw $e;
        }

        $lastModified = isset($info['updated'])
            ? new \DateTimeImmutable($info['updated'])
            : null;

        return new ObjectMetadata(
            key: $key,
            size: isset($info['size']) ? (int) $info['size'] : null,
            lastModified: $lastModified,
            mimeType: $info['contentType'] ?? null,
        );
    }

    protected function doPublicUrl(string $key): string
    {
        $prefixedKey = $this->prefixKey($key);

        if ($this->publicUrl !== null) {
            return rtrim($this->publicUrl, '/') . '/' . ltrim($prefixedKey, '/');
        }

        return sprintf('https://storage.googleapis.com/%s/%s', $this->bucket->name(), $prefixedKey);
    }

    protected function doTemporaryUrl(string $key, \DateTimeInterface $expiration): string
    {
        $object = $this->bucket->object($this->prefixKey($key));

        return $object->signedUrl($expiration, ['version' => 'v4']);
    }

    protected function doListContents(string $prefix, bool $recursive): iterable
    {
        $fullPrefix = $this->prefixKey($prefix);

        $options = ['prefix' => $fullPrefix];

        if (!$recursive) {
            $options['delimiter'] = '/';
        }

        foreach ($this->bucket->objects($options) as $object) {
            $info = $object->info();
            $objectKey = $this->stripPrefix($object->name());

            $lastModified = isset($info['updated'])
                ? new \DateTimeImmutable($info['updated'])
                : null;

            yield new ObjectMetadata(
                key: $objectKey,
                size: isset($info['size']) ? (int) $info['size'] : null,
                lastModified: $lastModified,
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
            || str_contains($message, 'No such object');
    }
}
