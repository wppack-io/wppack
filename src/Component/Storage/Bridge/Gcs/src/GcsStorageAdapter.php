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

namespace WpPack\Component\Storage\Bridge\Gcs;

use Google\Cloud\Storage\Bucket;
use WpPack\Component\Storage\Adapter\AbstractStorageAdapter;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\ObjectMetadata;
use WpPack\Component\Storage\Visibility;
use Google\Cloud\Core\Exception\NotFoundException;

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

    protected function doWrite(string $path, string $contents, array $metadata = []): void
    {
        $options = [
            'name' => $this->prefixPath($path),
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

    protected function doWriteStream(string $path, mixed $resource, array $metadata = []): void
    {
        $options = [
            'name' => $this->prefixPath($path),
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

    protected function doRead(string $path): string
    {
        $object = $this->bucket->object($this->prefixPath($path));

        try {
            return $object->downloadAsString();
        } catch (\Throwable $e) {
            if ($this->isNotFoundException($e)) {
                throw new ObjectNotFoundException($path, $e);
            }
            throw $e;
        }
    }

    protected function doReadStream(string $path): mixed
    {
        $object = $this->bucket->object($this->prefixPath($path));
        $stream = null;

        try {
            $body = $object->downloadAsStream();

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
        $this->bucket->object($this->prefixPath($path))->delete();
    }

    protected function doFileExists(string $path): bool
    {
        return $this->bucket->object($this->prefixPath($path))->exists();
    }

    protected function doCreateDirectory(string $path): void
    {
        $dirName = rtrim($this->prefixPath($path), '/') . '/';
        $this->bucket->upload('', ['name' => $dirName]);
    }

    protected function doDeleteDirectory(string $path): void
    {
        $dirPrefix = rtrim($this->prefixPath($path), '/') . '/';

        foreach ($this->bucket->objects(['prefix' => $dirPrefix]) as $object) {
            $object->delete();
        }
    }

    protected function doDirectoryExists(string $path): bool
    {
        $dirPrefix = rtrim($this->prefixPath($path), '/') . '/';
        $objects = $this->bucket->objects(['prefix' => $dirPrefix, 'maxResults' => 1]);

        foreach ($objects as $_) {
            return true;
        }

        return false;
    }

    protected function doCopy(string $source, string $destination): void
    {
        $this->bucket->object($this->prefixPath($source))->copy($this->bucket->name(), [
            'name' => $this->prefixPath($destination),
        ]);
    }

    protected function doMetadata(string $path): ObjectMetadata
    {
        $object = $this->bucket->object($this->prefixPath($path));

        try {
            $info = $object->info();
        } catch (\Throwable $e) {
            if ($this->isNotFoundException($e)) {
                throw new ObjectNotFoundException($path, $e);
            }
            throw $e;
        }

        $lastModified = isset($info['updated'])
            ? new \DateTimeImmutable($info['updated'])
            : null;

        return new ObjectMetadata(
            path: $path,
            size: isset($info['size']) ? (int) $info['size'] : null,
            lastModified: $lastModified,
            mimeType: $info['contentType'] ?? null,
        );
    }

    protected function doPublicUrl(string $path): string
    {
        $prefixedPath = $this->prefixPath($path);

        if ($this->publicUrl !== null) {
            return rtrim($this->publicUrl, '/') . '/' . ltrim($prefixedPath, '/');
        }

        return sprintf('https://storage.googleapis.com/%s/%s', $this->bucket->name(), $prefixedPath);
    }

    protected function doTemporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        $object = $this->bucket->object($this->prefixPath($path));

        return $object->signedUrl($expiration, ['version' => 'v4']);
    }

    protected function doTemporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
    {
        $object = $this->bucket->object($this->prefixPath($path));

        $signOptions = [
            'version' => 'v4',
            'method' => 'PUT',
        ];

        if (isset($options['Content-Type'])) {
            $signOptions['contentType'] = (string) $options['Content-Type'];
        }

        return $object->signedUrl($expiration, $signOptions);
    }

    protected function doSetVisibility(string $path, Visibility $visibility): void
    {
        $predefinedAcl = match ($visibility) {
            Visibility::PUBLIC => 'publicRead',
            Visibility::PRIVATE => 'private',
        };

        $object = $this->bucket->object($this->prefixPath($path));
        $object->update([], ['predefinedAcl' => $predefinedAcl]);
    }

    protected function doListContents(string $path, bool $deep): iterable
    {
        $fullPrefix = $this->prefixPath($path);

        $options = ['prefix' => $fullPrefix];

        if (!$deep) {
            $options['delimiter'] = '/';
        }

        foreach ($this->bucket->objects($options) as $object) {
            $info = $object->info();
            $objectPath = $this->stripPath($object->name());

            $lastModified = isset($info['updated'])
                ? new \DateTimeImmutable($info['updated'])
                : null;

            yield new ObjectMetadata(
                path: $objectPath,
                size: isset($info['size']) ? (int) $info['size'] : null,
                lastModified: $lastModified,
                mimeType: $info['contentType'] ?? null,
            );
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
        return $e instanceof NotFoundException;
    }
}
