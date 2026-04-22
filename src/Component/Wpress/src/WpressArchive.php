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

namespace WPPack\Component\Wpress;

use WPPack\Component\Wpress\ContentProcessor\ChainContentProcessor;
use WPPack\Component\Wpress\ContentProcessor\CompressedContentProcessor;
use WPPack\Component\Wpress\ContentProcessor\ContentProcessorInterface;
use WPPack\Component\Wpress\ContentProcessor\EncryptedContentProcessor;
use WPPack\Component\Wpress\ContentProcessor\PlainContentProcessor;
use WPPack\Component\Wpress\Exception\ArchiveException;
use WPPack\Component\Wpress\Exception\EntryNotFoundException;

final class WpressArchive implements \Countable
{
    public const CREATE = 1;

    private const CHUNK_READ_SIZE = 8192;
    private const CONFIG_ENTRIES = ['package.json', 'multisite.json'];

    /** @var resource */
    private $handle;

    private ContentProcessorInterface $contentProcessor;
    private ContentProcessorInterface $plainProcessor;

    /** @var array<string, int>|null Lazy-built index: path → header offset */
    private ?array $index = null;

    /** @var list<string> Entries marked for deletion */
    private array $pendingDeletes = [];

    /** @var list<array{path: string, callback: \Closure}> Entries pending append */
    private array $pendingAppends = [];

    private bool $modified = false;

    public function __construct(
        private readonly string $path,
        int $flags = 0,
        private readonly ?string $password = null,
    ) {
        $this->plainProcessor = new PlainContentProcessor();

        if ($flags & self::CREATE) {
            $handle = fopen($this->path, 'w+b');
            if ($handle === false) {
                throw new ArchiveException(\sprintf('Cannot create archive: %s', $this->path));
            }
            $this->handle = $handle;
            // Write EOF marker
            fwrite($this->handle, Header::eof()->toBinary());
            $this->contentProcessor = $this->buildContentProcessor();
        } else {
            if (!file_exists($this->path)) {
                throw new ArchiveException(\sprintf('Archive not found: %s', $this->path));
            }
            $handle = fopen($this->path, 'r+b');
            if ($handle === false) {
                throw new ArchiveException(\sprintf('Cannot open archive: %s', $this->path));
            }
            $this->handle = $handle;
            $this->contentProcessor = $this->buildContentProcessor();
        }
    }

    /**
     * @return \Generator<WpressEntry>
     */
    public function getEntries(): \Generator
    {
        fseek($this->handle, 0);

        while (!feof($this->handle)) {
            $headerData = fread($this->handle, Header::SIZE);

            if ($headerData === false || \strlen($headerData) < Header::SIZE) {
                break;
            }

            $header = Header::fromBinary($headerData);

            if ($header->isEof()) {
                break;
            }

            $contentOffset = ftell($this->handle);
            if ($contentOffset === false) {
                break;
            }
            $processor = $this->getProcessorForEntry($header->name);

            yield new WpressEntry($header, $this->handle, $contentOffset, $processor);

            // Skip content to next header
            fseek($this->handle, $contentOffset + $header->size);
        }
    }

    public function getEntry(string $path): WpressEntry
    {
        $this->buildIndex();

        if (!isset($this->index[$path])) {
            throw new EntryNotFoundException(\sprintf('Entry not found: %s', $path));
        }

        $offset = $this->index[$path];
        fseek($this->handle, $offset);

        $headerData = fread($this->handle, Header::SIZE);
        if ($headerData === false) {
            throw new EntryNotFoundException(\sprintf('Failed to read header for entry: %s', $path));
        }
        $header = Header::fromBinary($headerData);
        $contentOffset = ftell($this->handle);
        if ($contentOffset === false) {
            throw new EntryNotFoundException(\sprintf('Failed to read offset for entry: %s', $path));
        }
        $processor = $this->getProcessorForEntry($header->name);

        return new WpressEntry($header, $this->handle, $contentOffset, $processor);
    }

    public function count(): int
    {
        $this->buildIndex();

        return \count($this->index);
    }

    public function addFile(string $sourcePath, string $archivePath): void
    {
        if (!file_exists($sourcePath)) {
            throw new ArchiveException(\sprintf('Source file not found: %s', $sourcePath));
        }

        $this->pendingAppends[] = [
            'path' => $archivePath,
            'callback' => function ($handle) use ($sourcePath, $archivePath): void {
                $this->writeFileEntry($handle, $sourcePath, $archivePath);
            },
        ];

        $this->modified = true;
    }

    public function addDirectory(string $sourceDir, string $archivePrefix): void
    {
        $sourceDir = rtrim($sourceDir, '/');
        $archivePrefix = rtrim($archivePrefix, '/');

        if (!is_dir($sourceDir)) {
            throw new ArchiveException(\sprintf('Source directory not found: %s', $sourceDir));
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), \strlen($sourceDir) + 1);
            $archivePath = $archivePrefix . '/' . $relativePath;

            $this->addFile($file->getPathname(), $archivePath);
        }
    }

    public function addFromString(string $archivePath, string $content): void
    {
        $this->pendingAppends[] = [
            'path' => $archivePath,
            'callback' => function ($handle) use ($archivePath, $content): void {
                $this->writeStringEntry($handle, $archivePath, $content);
            },
        ];

        $this->modified = true;
    }

    public function deleteEntry(string $path): void
    {
        $this->pendingDeletes[] = $path;
        $this->modified = true;
    }

    public function deleteEntries(string $pattern): void
    {
        $this->buildIndex();

        foreach (array_keys($this->index) as $path) {
            if (fnmatch($pattern, $path)) {
                $this->pendingDeletes[] = $path;
            }
        }

        if ($this->pendingDeletes !== []) {
            $this->modified = true;
        }
    }

    public function extractTo(string $destination, ?string $filter = null): void
    {
        $destination = rtrim($destination, '/');

        foreach ($this->getEntries() as $entry) {
            $path = $entry->getPath();

            if ($filter !== null && !str_starts_with($path, $filter)) {
                continue;
            }

            $targetPath = $destination . '/' . $path;
            $targetDir = \dirname($targetPath);

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $source = $entry->getStream();
            $target = fopen($targetPath, 'wb');

            if ($target === false) {
                fclose($source);
                throw new ArchiveException(\sprintf('Cannot create file: %s', $targetPath));
            }

            stream_copy_to_stream($source, $target);
            fclose($source);
            fclose($target);

            // Restore mtime
            touch($targetPath, $entry->getMTime());
        }
    }

    public function close(): void
    {
        if (!$this->modified) {
            fclose($this->handle);

            return;
        }

        if ($this->pendingDeletes !== []) {
            $this->rewriteArchive();
        } else {
            $this->appendEntries();
        }

        fclose($this->handle);
        $this->modified = false;
    }

    private function buildContentProcessor(): ContentProcessorInterface
    {
        if ($this->password === null) {
            return $this->plainProcessor;
        }

        // Read package.json to determine encryption/compression settings
        $packageData = $this->readPackageJsonRaw();

        if ($packageData === null) {
            // New archive with password — default to encryption only
            return new EncryptedContentProcessor($this->password);
        }

        $config = json_decode($packageData, true);

        if (!\is_array($config)) {
            return new EncryptedContentProcessor($this->password);
        }

        $encrypted = $config['Encrypted'] ?? false;
        $compressionEnabled = $config['Compression']['Enabled'] ?? false;
        $compressionType = $config['Compression']['Type'] ?? 'gzip';

        if ($encrypted && $compressionEnabled) {
            return new ChainContentProcessor($this->password, $compressionType);
        }

        if ($encrypted) {
            return new EncryptedContentProcessor($this->password);
        }

        if ($compressionEnabled) {
            return new CompressedContentProcessor($compressionType);
        }

        return $this->plainProcessor;
    }

    private function readPackageJsonRaw(): ?string
    {
        fseek($this->handle, 0);

        while (!feof($this->handle)) {
            $headerData = fread($this->handle, Header::SIZE);

            if ($headerData === false || \strlen($headerData) < Header::SIZE) {
                return null;
            }

            $header = Header::fromBinary($headerData);

            if ($header->isEof()) {
                return null;
            }

            if ($header->name === 'package.json') {
                if ($header->size <= 0) {
                    return '{}';
                }

                $content = fread($this->handle, $header->size);

                return $content !== false ? $content : null;
            }

            // Skip this entry's content
            if (fseek($this->handle, ftell($this->handle) + $header->size) === -1) {
                return null;
            }
        }

        return null;
    }

    /**
     * @phpstan-assert !null $this->index
     */
    private function buildIndex(): void
    {
        if ($this->index !== null) {
            return;
        }

        $this->index = [];
        fseek($this->handle, 0);

        while (!feof($this->handle)) {
            $headerOffset = ftell($this->handle);
            if ($headerOffset === false) {
                break;
            }
            $headerData = fread($this->handle, Header::SIZE);

            if ($headerData === false || \strlen($headerData) < Header::SIZE) {
                break;
            }

            $header = Header::fromBinary($headerData);

            if ($header->isEof()) {
                break;
            }

            $this->index[$header->getPath()] = $headerOffset;

            // Skip content
            fseek($this->handle, ftell($this->handle) + $header->size);
        }
    }

    private function getProcessorForEntry(string $name): ContentProcessorInterface
    {
        // Config entries are always plaintext
        if (\in_array($name, self::CONFIG_ENTRIES, true)) {
            return $this->plainProcessor;
        }

        return $this->contentProcessor;
    }

    private function appendEntries(): void
    {
        // Find EOF marker position and overwrite it
        $eofOffset = $this->findEofOffset();

        if ($eofOffset !== null) {
            fseek($this->handle, $eofOffset);
        } else {
            fseek($this->handle, 0, \SEEK_END);
        }

        foreach ($this->pendingAppends as $entry) {
            ($entry['callback'])($this->handle);
        }

        // Write new EOF marker
        fwrite($this->handle, Header::eof()->toBinary());

        $this->pendingAppends = [];
        $this->index = null;
    }

    private function rewriteArchive(): void
    {
        $deletePaths = array_flip($this->pendingDeletes);

        $tempPath = $this->path . '.tmp';
        $tempHandle = fopen($tempPath, 'w+b');

        if ($tempHandle === false) {
            throw new ArchiveException(\sprintf('Cannot create temporary file: %s', $tempPath));
        }

        try {
            // Copy entries that are not in the delete list
            fseek($this->handle, 0);

            while (!feof($this->handle)) {
                $headerData = fread($this->handle, Header::SIZE);

                if ($headerData === false || \strlen($headerData) < Header::SIZE) {
                    break;
                }

                $header = Header::fromBinary($headerData);

                if ($header->isEof()) {
                    break;
                }

                $path = $header->getPath();

                if (isset($deletePaths[$path])) {
                    // Skip this entry
                    fseek($this->handle, ftell($this->handle) + $header->size);

                    continue;
                }

                // Copy header + content
                fwrite($tempHandle, $headerData);

                $remaining = $header->size;

                while ($remaining > 0) {
                    $readSize = min(self::CHUNK_READ_SIZE, $remaining);
                    $chunk = fread($this->handle, $readSize);

                    if ($chunk === false) {
                        break;
                    }

                    fwrite($tempHandle, $chunk);
                    $remaining -= \strlen($chunk);
                }
            }

            // Append new entries
            foreach ($this->pendingAppends as $entry) {
                ($entry['callback'])($tempHandle);
            }

            // Write EOF marker
            fwrite($tempHandle, Header::eof()->toBinary());
            fclose($tempHandle);

            // Replace original file
            fclose($this->handle);

            if (!rename($tempPath, $this->path)) {
                throw new ArchiveException('Failed to replace archive with updated version.');
            }

            $handle = fopen($this->path, 'r+b');

            if ($handle === false) {
                throw new ArchiveException(\sprintf('Cannot reopen archive: %s', $this->path));
            }
            $this->handle = $handle;
        } catch (\Throwable $e) {
            if (is_resource($tempHandle)) {
                fclose($tempHandle);
            }

            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            throw $e;
        }

        $this->pendingDeletes = [];
        $this->pendingAppends = [];
        $this->index = null;
    }

    private function findEofOffset(): ?int
    {
        fseek($this->handle, 0);

        while (!feof($this->handle)) {
            $offset = ftell($this->handle);
            if ($offset === false) {
                return null;
            }

            $headerData = fread($this->handle, Header::SIZE);

            if ($headerData === false || \strlen($headerData) < Header::SIZE) {
                return null;
            }

            $header = Header::fromBinary($headerData);

            if ($header->isEof()) {
                return $offset;
            }

            fseek($this->handle, ftell($this->handle) + $header->size);
        }

        return null;
    }

    /**
     * @param resource $handle
     */
    private function writeFileEntry($handle, string $sourcePath, string $archivePath): void
    {
        $name = basename($archivePath);
        $lastSlash = strrpos($archivePath, '/');
        $prefix = $lastSlash !== false ? substr($archivePath, 0, $lastSlash) : '.';

        $processor = $this->getProcessorForEntry($name);

        // For plaintext processor, we can stream directly
        if ($processor instanceof PlainContentProcessor) {
            $this->writeFileEntryPlain($handle, $sourcePath, $archivePath);

            return;
        }

        // For encrypted/compressed, we need to process the entire content
        $content = file_get_contents($sourcePath);

        if ($content === false) {
            throw new ArchiveException(\sprintf('Cannot read source file: %s', $sourcePath));
        }

        $encoded = $processor->encode($content);
        $mtime = filemtime($sourcePath) ?: time();

        $header = new Header(
            name: $name,
            size: \strlen($encoded),
            mtime: $mtime,
            prefix: $prefix,
        );

        fwrite($handle, $header->toBinary());
        fwrite($handle, $encoded);
    }

    /**
     * @param resource $handle
     */
    private function writeFileEntryPlain($handle, string $sourcePath, string $archivePath): void
    {
        $sourceHandle = fopen($sourcePath, 'rb');

        if ($sourceHandle === false) {
            throw new ArchiveException(\sprintf('Cannot read source file: %s', $sourcePath));
        }

        $size = filesize($sourcePath);
        if ($size === false) {
            throw new ArchiveException(\sprintf('Cannot determine size of source file: %s', $sourcePath));
        }
        $mtime = filemtime($sourcePath) ?: time();

        $header = Header::fromPath($archivePath, $size, $mtime);
        fwrite($handle, $header->toBinary());

        // Stream copy in chunks
        while (!feof($sourceHandle)) {
            $chunk = fread($sourceHandle, self::CHUNK_READ_SIZE);

            if ($chunk === false || $chunk === '') {
                break;
            }

            fwrite($handle, $chunk);
        }

        fclose($sourceHandle);
    }

    /**
     * @param resource $handle
     */
    private function writeStringEntry($handle, string $archivePath, string $content): void
    {
        $name = basename($archivePath);
        $processor = $this->getProcessorForEntry($name);
        $encoded = $processor->encode($content);

        $header = Header::fromPath($archivePath, \strlen($encoded));
        fwrite($handle, $header->toBinary());
        fwrite($handle, $encoded);
    }
}
