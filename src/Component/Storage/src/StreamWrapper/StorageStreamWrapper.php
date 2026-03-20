<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\StreamWrapper;

use WpPack\Component\Storage\Adapter\StorageAdapterInterface;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\ObjectMetadata;

final class StorageStreamWrapper
{
    /** @var resource|null */
    public $context;

    /** @var array<string, StorageAdapterInterface> */
    private static array $adapters = [];

    /** @var array<string, StatCache> */
    private static array $statCaches = [];

    /** @var resource|null */
    private $body;

    private string $mode = '';
    private string $protocol = '';
    private string $path = '';
    private bool $writable = false;
    private bool $readable = false;
    private bool $dirty = false;

    /** @var list<string> */
    private array $dirEntries = [];
    private int $dirIndex = 0;

    public static function register(string $protocol, StorageAdapterInterface $adapter): void
    {
        if (\in_array($protocol, stream_get_wrappers(), true)) {
            stream_wrapper_unregister($protocol);
        }

        stream_wrapper_register($protocol, self::class, \STREAM_IS_URL);
        self::$adapters[$protocol] = $adapter;
        self::$statCaches[$protocol] = new StatCache();
    }

    public static function unregister(string $protocol): void
    {
        if (\in_array($protocol, stream_get_wrappers(), true)) {
            stream_wrapper_unregister($protocol);
        }

        unset(self::$adapters[$protocol], self::$statCaches[$protocol]);
    }

    public static function getAdapter(string $protocol): ?StorageAdapterInterface
    {
        return self::$adapters[$protocol] ?? null;
    }

    public static function getStatCache(string $protocol): ?StatCache
    {
        return self::$statCaches[$protocol] ?? null;
    }

    // ──────────────────────────────────────────────
    // Stream operations
    // ──────────────────────────────────────────────

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->initProtocol($path);
        $this->path = $this->parsePath($path);
        $this->mode = rtrim($mode, 'bt');

        $adapter = $this->getAdapterForProtocol();
        if ($adapter === null) {
            return $this->triggerError('No adapter registered for protocol: ' . $this->protocol, $options);
        }

        $this->readable = \in_array($this->mode, ['r', 'r+', 'w+', 'a+', 'x+', 'c+'], true);
        $this->writable = $this->mode !== 'r';

        return match ($this->mode) {
            'r' => $this->openReadOnly($adapter),
            'r+' => $this->openReadWrite($adapter),
            'w' => $this->openWriteOnly(),
            'w+' => $this->openWriteNew(),
            'a' => $this->openAppend($adapter, false),
            'a+' => $this->openAppend($adapter, true),
            'x' => $this->openExclusive($adapter, false),
            'x+' => $this->openExclusive($adapter, true),
            'c' => $this->openCreate($adapter, false),
            'c+' => $this->openCreate($adapter, true),
            default => $this->triggerError('Unsupported mode: ' . $this->mode, \STREAM_REPORT_ERRORS),
        };
    }

    public function stream_read(int $count): string|false
    {
        if ($this->body === null || !$this->readable) {
            return false;
        }

        $data = fread($this->body, $count);

        return $data !== false ? $data : false;
    }

    public function stream_write(string $data): int
    {
        if ($this->body === null || !$this->writable) {
            return 0;
        }

        $written = fwrite($this->body, $data);

        if ($written !== false && $written > 0) {
            $this->dirty = true;
        }

        return $written !== false ? $written : 0;
    }

    public function stream_close(): void
    {
        if ($this->body !== null && $this->writable && $this->dirty) {
            $this->flush();
        }

        if ($this->body !== null) {
            fclose($this->body);
            $this->body = null;
        }
    }

    public function stream_eof(): bool
    {
        if ($this->body === null) {
            return true;
        }

        return feof($this->body);
    }

    public function stream_tell(): int|false
    {
        if ($this->body === null) {
            return false;
        }

        return ftell($this->body);
    }

    public function stream_seek(int $offset, int $whence = \SEEK_SET): bool
    {
        if ($this->body === null) {
            return false;
        }

        return fseek($this->body, $offset, $whence) === 0;
    }

    /**
     * @return array<string|int, int|string>|false
     */
    public function stream_stat(): array|false
    {
        if ($this->body === null) {
            return false;
        }

        $stat = $this->buildStatTemplate();
        $fstat = fstat($this->body);

        if ($fstat !== false) {
            $stat[7] = $fstat['size'];
            $stat['size'] = $fstat['size'];
        }

        $stat[2] = $this->writable ? 0100666 : 0100444;
        $stat['mode'] = $stat[2];

        return $stat;
    }

    public function stream_flush(): bool
    {
        if ($this->body === null) {
            return false;
        }

        if (!$this->writable || !$this->dirty) {
            return true;
        }

        return $this->flush();
    }

    public function stream_truncate(int $newSize): bool
    {
        if ($this->body === null || !$this->writable) {
            return false;
        }

        $this->dirty = true;

        return ftruncate($this->body, $newSize);
    }

    public function stream_lock(int $operation): bool
    {
        return true;
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return false;
    }

    // ──────────────────────────────────────────────
    // URL operations
    // ──────────────────────────────────────────────

    /**
     * @return array<string|int, int|string>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        $this->initProtocol($path);
        $storagePath = $this->parsePath($path);

        // Check stat cache
        $statCache = self::$statCaches[$this->protocol] ?? null;
        if ($statCache !== null) {
            $cached = $statCache->get($path);
            if ($cached !== null) {
                return $cached;
            }
        }

        $adapter = $this->getAdapterForProtocol();
        if ($adapter === null) {
            return false;
        }

        // Try metadata() first (file existence)
        try {
            $metadata = $adapter->metadata($storagePath);
            $stat = $this->buildFileStat($metadata);

            $statCache?->set($path, $stat);

            return $stat;
        } catch (ObjectNotFoundException) {
            // Not a file — check if it's a directory
        } catch (\Throwable) {
            return false;
        }

        // Check directory existence
        try {
            if ($adapter->directoryExists($storagePath)) {
                return $this->buildDirectoryStat();
            }
        } catch (\Throwable) {
            // Fall through
        }

        if ($flags & \STREAM_URL_STAT_QUIET) {
            return false;
        }

        return false;
    }

    public function unlink(string $path): bool
    {
        $this->initProtocol($path);
        $storagePath = $this->parsePath($path);

        $adapter = $this->getAdapterForProtocol();
        if ($adapter === null) {
            return false;
        }

        try {
            $adapter->delete($storagePath);
            self::$statCaches[$this->protocol]->remove($path);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function rename(string $pathFrom, string $pathTo): bool
    {
        $this->initProtocol($pathFrom);
        $storagePathFrom = $this->parsePath($pathFrom);
        $storagePathTo = $this->parsePath($pathTo);

        $adapter = $this->getAdapterForProtocol();
        if ($adapter === null) {
            return false;
        }

        try {
            $adapter->move($storagePathFrom, $storagePathTo);
            self::$statCaches[$this->protocol]->remove($pathFrom);
            self::$statCaches[$this->protocol]->remove($pathTo);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        $this->initProtocol($path);
        $storagePath = $this->parsePath($path);

        $adapter = $this->getAdapterForProtocol();
        if ($adapter === null) {
            return false;
        }

        try {
            $adapter->createDirectory($storagePath);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function rmdir(string $path, int $options): bool
    {
        $this->initProtocol($path);
        $storagePath = $this->parsePath($path);

        $adapter = $this->getAdapterForProtocol();
        if ($adapter === null) {
            return false;
        }

        try {
            $adapter->deleteDirectory($storagePath);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ──────────────────────────────────────────────
    // Directory operations
    // ──────────────────────────────────────────────

    public function dir_opendir(string $path, int $options): bool
    {
        $this->initProtocol($path);
        $prefix = $this->parsePath($path);

        if ($prefix !== '' && !str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        $adapter = $this->getAdapterForProtocol();
        if ($adapter === null) {
            return false;
        }

        try {
            $this->dirEntries = [];
            $this->dirIndex = 0;
            $seen = [];

            // Use deep listing and extract first-level entries
            foreach ($adapter->listContents($prefix, true) as $item) {
                $relativePath = substr($item->path, \strlen($prefix));
                $relativePath = ltrim($relativePath, '/');

                if ($relativePath === '') {
                    continue;
                }

                // Extract the first segment only
                $slashPos = strpos($relativePath, '/');
                $entry = $slashPos !== false ? substr($relativePath, 0, $slashPos) : $relativePath;

                if ($entry !== '' && !isset($seen[$entry])) {
                    $seen[$entry] = true;
                    $this->dirEntries[] = $entry;

                    // Pre-cache stat for listed file objects (not directories)
                    $statCache = self::$statCaches[$this->protocol] ?? null;
                    if ($statCache !== null && $slashPos === false) {
                        $fullPath = $this->protocol . '://' . $item->path;
                        $stat = $this->buildFileStat($item);
                        $statCache->set($fullPath, $stat);
                    }
                }
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function dir_readdir(): string|false
    {
        if ($this->dirIndex >= \count($this->dirEntries)) {
            return false;
        }

        return $this->dirEntries[$this->dirIndex++];
    }

    public function dir_closedir(): bool
    {
        $this->dirEntries = [];
        $this->dirIndex = 0;

        return true;
    }

    public function dir_rewinddir(): bool
    {
        $this->dirIndex = 0;

        return true;
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    private function initProtocol(string $path): void
    {
        $parts = explode('://', $path, 2);
        $this->protocol = strtolower($parts[0]);
    }

    private function parsePath(string $path): string
    {
        $parts = explode('://', $path, 2);

        return $parts[1] ?? '';
    }

    private function getAdapterForProtocol(): ?StorageAdapterInterface
    {
        return self::$adapters[$this->protocol] ?? null;
    }

    private function openReadOnly(StorageAdapterInterface $adapter): bool
    {
        return $this->loadFromStorage($adapter);
    }

    private function openReadWrite(StorageAdapterInterface $adapter): bool
    {
        return $this->loadFromStorage($adapter);
    }

    /**
     * Load file content from storage using stream-based transfer.
     *
     * Uses readStream() + stream_copy_to_stream() for chunked transfer,
     * avoiding loading the entire file content into a PHP string.
     */
    private function loadFromStorage(StorageAdapterInterface $adapter): bool
    {
        $stream = null;

        try {
            $stream = $adapter->readStream($this->path);
            $this->body = $this->createTempStream();
            stream_copy_to_stream($stream, $this->body);
            fclose($stream);
            $stream = null;
            rewind($this->body);

            return true;
        } catch (\Throwable) {
            if (\is_resource($stream)) {
                fclose($stream);
            }

            return false;
        }
    }

    private function openWriteOnly(): bool
    {
        $this->body = $this->createTempStream();
        $this->dirty = true; // w mode always writes (truncate semantics)

        return true;
    }

    private function openWriteNew(): bool
    {
        $this->body = $this->createTempStream();
        $this->dirty = true; // w+ mode always writes (truncate semantics)

        return true;
    }

    private function openAppend(StorageAdapterInterface $adapter, bool $readable): bool
    {
        $this->body = $this->createTempStream();
        $stream = null;

        try {
            $stream = $adapter->readStream($this->path);
            stream_copy_to_stream($stream, $this->body);
            fclose($stream);
            $stream = null;
        } catch (ObjectNotFoundException) {
            // File does not exist yet — start empty
        } catch (\Throwable) {
            if (\is_resource($stream)) {
                fclose($stream);
            }

            return false;
        }

        // Seek to end for appending
        fseek($this->body, 0, \SEEK_END);

        return true;
    }

    private function openExclusive(StorageAdapterInterface $adapter, bool $readable): bool
    {
        try {
            if ($adapter->fileExists($this->path)) {
                return $this->triggerError('File already exists: ' . $this->path, \STREAM_REPORT_ERRORS);
            }
        } catch (\Throwable) {
            return false;
        }

        $this->body = $this->createTempStream();
        $this->dirty = true; // x mode creates a new file (flush even if empty)

        return true;
    }

    private function openCreate(StorageAdapterInterface $adapter, bool $readable): bool
    {
        $this->body = $this->createTempStream();

        if ($readable) {
            $stream = null;

            try {
                $stream = $adapter->readStream($this->path);
                stream_copy_to_stream($stream, $this->body);
                fclose($stream);
                $stream = null;
                rewind($this->body);
            } catch (ObjectNotFoundException) {
                // File does not exist — start empty
            } catch (\Throwable) {
                if (\is_resource($stream)) {
                    fclose($stream);
                }

                return false;
            }
        }

        return true;
    }

    private function flush(): bool
    {
        $adapter = $this->getAdapterForProtocol();
        if ($adapter === null || $this->body === null) {
            return false;
        }

        try {
            rewind($this->body);
            $adapter->writeStream($this->path, $this->body);
            self::$statCaches[$this->protocol]->remove($this->protocol . '://' . $this->path);
            $this->dirty = false;

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return resource
     */
    private function createTempStream(): mixed
    {
        $stream = fopen('php://temp', 'r+b');
        \assert($stream !== false);

        return $stream;
    }

    private function triggerError(string $message, int $flags): bool
    {
        if ($flags & \STREAM_REPORT_ERRORS) {
            trigger_error($message, \E_USER_WARNING);
        }

        return false;
    }

    /**
     * @return array<string|int, int|string>
     */
    private function buildStatTemplate(): array
    {
        return [
            0 => 0, 'dev' => 0,
            1 => 0, 'ino' => 0,
            2 => 0, 'mode' => 0,
            3 => 0, 'nlink' => 0,
            4 => 0, 'uid' => 0,
            5 => 0, 'gid' => 0,
            6 => -1, 'rdev' => -1,
            7 => 0, 'size' => 0,
            8 => 0, 'atime' => 0,
            9 => 0, 'mtime' => 0,
            10 => 0, 'ctime' => 0,
            11 => -1, 'blksize' => -1,
            12 => -1, 'blocks' => -1,
        ];
    }

    /**
     * @return array<string|int, int|string>
     */
    private function buildDirectoryStat(): array
    {
        $stat = $this->buildStatTemplate();
        // 0040777 = directory with full permissions
        $stat[2] = 0040777;
        $stat['mode'] = 0040777;

        return $stat;
    }

    /**
     * @return array<string|int, int|string>
     */
    private function buildFileStat(ObjectMetadata $metadata): array
    {
        $stat = $this->buildStatTemplate();
        // 0100666 = regular file with rw-rw-rw- permissions
        $stat[2] = 0100666;
        $stat['mode'] = 0100666;
        $stat[7] = $metadata->size ?? 0;
        $stat['size'] = $metadata->size ?? 0;

        if ($metadata->lastModified !== null) {
            $time = $metadata->lastModified->getTimestamp();
            $stat[8] = $time;
            $stat['atime'] = $time;
            $stat[9] = $time;
            $stat['mtime'] = $time;
            $stat[10] = $time;
            $stat['ctime'] = $time;
        }

        return $stat;
    }
}
