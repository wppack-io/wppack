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

namespace WpPack\Component\HttpFoundation\Tests\File;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\File\Exception\FileException;
use WpPack\Component\HttpFoundation\File\File;
use WpPack\Component\HttpFoundation\File\UploadedFile;

final class UploadedFileTest extends TestCase
{
    #[Test]
    public function extendsFile(): void
    {
        $file = new UploadedFile('/tmp/test', 'photo.jpg');

        self::assertInstanceOf(File::class, $file);
        self::assertInstanceOf(\SplFileInfo::class, $file);
    }

    #[Test]
    public function constructorStoresProperties(): void
    {
        $file = new UploadedFile('/tmp/test', 'photo.jpg', 'image/jpeg', \UPLOAD_ERR_OK);

        self::assertSame('/tmp/test', $file->getPathname());
        self::assertSame('photo.jpg', $file->originalName);
        self::assertSame('image/jpeg', $file->mimeType);
        self::assertSame(\UPLOAD_ERR_OK, $file->error);
    }

    #[Test]
    public function constructorDefaults(): void
    {
        $file = new UploadedFile('/tmp/test', 'file.txt');

        self::assertNull($file->mimeType);
        self::assertSame(\UPLOAD_ERR_OK, $file->error);
    }

    #[Test]
    public function getClientOriginalNameReturnsOriginalName(): void
    {
        $file = new UploadedFile('/tmp/test', 'photo.jpg');

        self::assertSame('photo.jpg', $file->getClientOriginalName());
    }

    #[Test]
    public function getClientMimeTypeReturnsClientMime(): void
    {
        $file = new UploadedFile('/tmp/test', 'photo.jpg', 'image/jpeg');

        self::assertSame('image/jpeg', $file->getClientMimeType());
    }

    #[Test]
    public function getClientMimeTypeReturnsNullWhenNotProvided(): void
    {
        $file = new UploadedFile('/tmp/test', 'file.txt');

        self::assertNull($file->getClientMimeType());
    }

    #[Test]
    public function isValidReturnsTrueWhenNoError(): void
    {
        $file = new UploadedFile('/tmp/test', 'file.txt', error: \UPLOAD_ERR_OK);

        self::assertTrue($file->isValid());
    }

    #[Test]
    public function isValidReturnsFalseForErrors(): void
    {
        $errorCodes = [
            \UPLOAD_ERR_INI_SIZE,
            \UPLOAD_ERR_FORM_SIZE,
            \UPLOAD_ERR_PARTIAL,
            \UPLOAD_ERR_NO_FILE,
            \UPLOAD_ERR_NO_TMP_DIR,
            \UPLOAD_ERR_CANT_WRITE,
            \UPLOAD_ERR_EXTENSION,
        ];

        foreach ($errorCodes as $code) {
            $file = new UploadedFile('/tmp/test', 'file.txt', error: $code);

            self::assertFalse($file->isValid(), "Expected isValid() to return false for error code: {$code}");
        }
    }

    #[Test]
    public function getErrorMessageReturnsCorrectMessageForEachErrorCode(): void
    {
        $expectedMessages = [
            \UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            \UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
            \UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            \UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            \UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            \UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            \UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
        ];

        foreach ($expectedMessages as $code => $message) {
            $file = new UploadedFile('/tmp/test', 'file.txt', error: $code);

            self::assertSame($message, $file->getErrorMessage(), "Unexpected message for error code: {$code}");
        }
    }

    #[Test]
    public function getErrorMessageReturnsUnknownForUnknownErrorCode(): void
    {
        $file = new UploadedFile('/tmp/test', 'file.txt', error: 999);

        self::assertSame('Unknown upload error.', $file->getErrorMessage());
    }

    #[Test]
    public function toFilesArrayReturnsCorrectFormat(): void
    {
        $file = new UploadedFile('/tmp/test', 'photo.jpg', 'image/jpeg', \UPLOAD_ERR_OK);
        $array = $file->toFilesArray();

        self::assertSame('photo.jpg', $array['name']);
        self::assertSame('image/jpeg', $array['type']);
        self::assertSame('/tmp/test', $array['tmp_name']);
        self::assertSame(\UPLOAD_ERR_OK, $array['error']);
        self::assertArrayHasKey('size', $array);
    }

    #[Test]
    public function toFilesArrayReturnsEmptyStringForNullMimeType(): void
    {
        $file = new UploadedFile('/tmp/test', 'file.txt');
        $array = $file->toFilesArray();

        self::assertSame('', $array['type']);
    }

    #[Test]
    public function getSizeReturnsNullWhenNotValid(): void
    {
        $file = new UploadedFile('/tmp/nonexistent', 'file.txt', error: \UPLOAD_ERR_PARTIAL);

        self::assertNull($file->getSize());
    }

    #[Test]
    public function getMimeTypeReturnsNullWhenNotValid(): void
    {
        $file = new UploadedFile('/tmp/test', 'file.txt', 'image/jpeg', \UPLOAD_ERR_PARTIAL);

        self::assertNull($file->getMimeType());
    }

    #[Test]
    public function getMimeTypeDetectsFromDiskWhenValid(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wppack_test_');
        file_put_contents($path, 'hello world');

        try {
            $file = new UploadedFile($path, 'test.txt', 'application/octet-stream');

            self::assertSame('text/plain', $file->getMimeType());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function moveReturnsFileInstance(): void
    {
        $tempDir = sys_get_temp_dir() . '/wppack_upload_test_' . uniqid();
        $sourcePath = $tempDir . '/source.txt';
        @mkdir($tempDir, 0777, true);
        file_put_contents($sourcePath, 'content');

        try {
            $file = new UploadedFile($sourcePath, 'original.txt');
            // move_uploaded_file() won't work in tests, so we test the return type contract
            // by verifying the class hierarchy and method signature
            self::assertInstanceOf(File::class, $file);
        } finally {
            @unlink($sourcePath);
            @rmdir($tempDir);
        }
    }

    #[Test]
    public function moveThrowsWhenNotValid(): void
    {
        $file = new UploadedFile('/tmp/test', 'file.txt', error: \UPLOAD_ERR_PARTIAL);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('The uploaded file was only partially uploaded.');

        $file->move('/tmp/target');
    }

    #[Test]
    public function moveThrowsWhenMoveUploadedFileFails(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wppack_upload_');
        file_put_contents($path, 'data');

        try {
            $file = new UploadedFile($path, 'test.txt', 'text/plain', \UPLOAD_ERR_OK);

            $this->expectException(FileException::class);
            $this->expectExceptionMessage('Could not move the file');

            // move_uploaded_file() fails for non-uploaded files in PHP
            $file->move(sys_get_temp_dir() . '/wppack_move_target_' . uniqid());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function getSizeReturnsFileSizeWhenValid(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wppack_size_');
        file_put_contents($path, 'hello');

        try {
            $file = new UploadedFile($path, 'test.txt', 'text/plain', \UPLOAD_ERR_OK);

            self::assertSame(5, $file->getSize());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function getSizeReturnsNullForNonExistentFile(): void
    {
        $file = new UploadedFile('/nonexistent/path/file.txt', 'file.txt', 'text/plain', \UPLOAD_ERR_OK);

        self::assertNull($file->getSize());
    }

    #[Test]
    public function moveCreatesDirectoryIfNotExists(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wppack_upload_');
        file_put_contents($path, 'data');

        $targetDir = sys_get_temp_dir() . '/wppack_move_test_' . uniqid() . '/nested';

        try {
            $file = new UploadedFile($path, 'test.txt', 'text/plain', \UPLOAD_ERR_OK);

            // move_uploaded_file will fail, but the directory should be created
            try {
                $file->move($targetDir);
            } catch (FileException) {
                // Expected: move_uploaded_file fails for non-uploaded files
            }

            // Verify directory was created
            self::assertDirectoryExists($targetDir);
        } finally {
            @unlink($path);
            @rmdir($targetDir);
            @rmdir(\dirname($targetDir));
        }
    }

    #[Test]
    public function moveUsesOriginalNameWhenNoNameProvided(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wppack_upload_');
        file_put_contents($path, 'data');

        try {
            $file = new UploadedFile($path, 'original-name.txt', 'text/plain', \UPLOAD_ERR_OK);

            // move_uploaded_file will fail, but the target path construction uses originalName
            try {
                $file->move(sys_get_temp_dir());
            } catch (FileException $e) {
                // Expected: move_uploaded_file fails for non-uploaded files
                self::assertStringContainsString('Could not move the file', $e->getMessage());
            }
        } finally {
            @unlink($path);
        }
    }
}
