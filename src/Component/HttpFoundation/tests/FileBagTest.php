<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\File\UploadedFile;
use WpPack\Component\HttpFoundation\FileBag;

final class FileBagTest extends TestCase
{
    #[Test]
    public function constructsFromSingleFileArray(): void
    {
        $bag = new FileBag([
            'avatar' => [
                'name' => 'test.txt',
                'type' => 'text/plain',
                'tmp_name' => '/tmp/x',
                'error' => \UPLOAD_ERR_OK,
            ],
        ]);

        $file = $bag->get('avatar');

        self::assertInstanceOf(UploadedFile::class, $file);
        self::assertSame('/tmp/x', $file->getPathname());
        self::assertSame('test.txt', $file->originalName);
        self::assertSame('text/plain', $file->mimeType);
        self::assertSame(\UPLOAD_ERR_OK, $file->error);
    }

    #[Test]
    public function constructsFromMultiFileArray(): void
    {
        $bag = new FileBag([
            'documents' => [
                'name' => ['doc1.pdf', 'doc2.pdf'],
                'type' => ['application/pdf', 'application/pdf'],
                'tmp_name' => ['/tmp/a', '/tmp/b'],
                'error' => [\UPLOAD_ERR_OK, \UPLOAD_ERR_OK],
            ],
        ]);

        $files = $bag->get('documents');

        self::assertIsArray($files);
        self::assertCount(2, $files);
        self::assertInstanceOf(UploadedFile::class, $files[0]);
        self::assertSame('/tmp/a', $files[0]->getPathname());
        self::assertSame('doc1.pdf', $files[0]->originalName);
        self::assertInstanceOf(UploadedFile::class, $files[1]);
        self::assertSame('/tmp/b', $files[1]->getPathname());
        self::assertSame('doc2.pdf', $files[1]->originalName);
    }

    #[Test]
    public function getReturnsNullForMissingKey(): void
    {
        $bag = new FileBag();

        self::assertNull($bag->get('missing'));
    }

    #[Test]
    public function hasReturnsTrueForExistingKey(): void
    {
        $bag = new FileBag([
            'file' => [
                'name' => 'test.txt',
                'type' => 'text/plain',
                'tmp_name' => '/tmp/x',
                'error' => \UPLOAD_ERR_OK,
            ],
        ]);

        self::assertTrue($bag->has('file'));
    }

    #[Test]
    public function hasReturnsFalseForMissingKey(): void
    {
        $bag = new FileBag();

        self::assertFalse($bag->has('missing'));
    }

    #[Test]
    public function allReturnsAllFiles(): void
    {
        $bag = new FileBag([
            'a' => ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/a', 'error' => \UPLOAD_ERR_OK],
            'b' => ['name' => 'b.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/b', 'error' => \UPLOAD_ERR_OK],
        ]);

        $all = $bag->all();

        self::assertCount(2, $all);
        self::assertArrayHasKey('a', $all);
        self::assertArrayHasKey('b', $all);
    }

    #[Test]
    public function countReturnsFileCount(): void
    {
        $bag = new FileBag([
            'a' => ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/a', 'error' => \UPLOAD_ERR_OK],
            'b' => ['name' => 'b.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/b', 'error' => \UPLOAD_ERR_OK],
        ]);

        self::assertSame(2, $bag->count());
    }

    #[Test]
    public function countReturnsZeroForEmptyBag(): void
    {
        $bag = new FileBag();

        self::assertSame(0, $bag->count());
    }

    #[Test]
    public function createFromGlobalsUsesFilesSuperglobal(): void
    {
        $originalFiles = $_FILES;

        try {
            $_FILES = [
                'upload' => [
                    'name' => 'global.txt',
                    'type' => 'text/plain',
                    'tmp_name' => '/tmp/global',
                    'error' => \UPLOAD_ERR_OK,
                ],
            ];

            $bag = FileBag::createFromGlobals();

            self::assertTrue($bag->has('upload'));
            $file = $bag->get('upload');
            self::assertInstanceOf(UploadedFile::class, $file);
            self::assertSame('global.txt', $file->originalName);
        } finally {
            $_FILES = $originalFiles;
        }
    }

    #[Test]
    public function throwsForInvalidFileData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file data provided.');

        new FileBag(['file' => 'invalid_string']);
    }

    #[Test]
    public function throwsForMalformedFileArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file data provided.');

        new FileBag(['file' => ['name' => 'test.txt']]);
    }

    #[Test]
    public function acceptsUploadedFileInstance(): void
    {
        $uploaded = new UploadedFile('/tmp/test', 'direct.txt', 'text/plain');
        $bag = new FileBag(['doc' => $uploaded]);

        self::assertSame($uploaded, $bag->get('doc'));
    }
}
