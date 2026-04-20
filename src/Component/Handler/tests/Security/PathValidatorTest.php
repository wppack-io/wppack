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

namespace WPPack\Component\Handler\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Handler\Exception\SecurityException;
use WPPack\Component\Handler\Security\PathValidator;

final class PathValidatorTest extends TestCase
{
    private PathValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PathValidator(sys_get_temp_dir());
    }

    #[Test]
    public function validPath(): void
    {
        $result = $this->validator->validate('/index.php');

        self::assertSame('/index.php', $result);
    }

    #[Test]
    public function nullByteDetected(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Null byte');

        $this->validator->validate("/file\0.php");
    }

    #[Test]
    #[DataProvider('traversalPatterns')]
    public function directoryTraversalBlocked(string $path): void
    {
        $this->expectException(SecurityException::class);

        $this->validator->validate($path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function traversalPatterns(): iterable
    {
        yield 'dot-dot-slash' => ['/../etc/passwd'];
        yield 'dot-dot-backslash' => ['/..\\etc\\passwd'];
        yield 'encoded' => ['/%2e%2e/etc/passwd'];
        yield 'double-encoded' => ['/%252e%252e%252f'];
    }

    #[Test]
    public function invalidCharactersBlocked(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid characters');

        $this->validator->validate("/path/with\x01control");
    }

    #[Test]
    public function filePathWithinWebRoot(): void
    {
        $tmpDir = sys_get_temp_dir();
        $testFile = tempnam($tmpDir, 'handler_test_');

        try {
            $result = $this->validator->validateFilePath($testFile);
            self::assertSame(realpath($testFile), $result);
        } finally {
            unlink($testFile);
        }
    }

    #[Test]
    public function invalidWebRootThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PathValidator('/nonexistent/path/that/does/not/exist');
    }

    #[Test]
    public function hiddenPathDetection(): void
    {
        self::assertTrue($this->validator->isHiddenPath('/.git/config'));
        self::assertTrue($this->validator->isHiddenPath('/path/.env'));
        self::assertFalse($this->validator->isHiddenPath('/path/file.txt'));
        self::assertFalse($this->validator->isHiddenPath('/'));
    }

    #[Test]
    public function validateFilePathWithoutSymlinkCheckAcceptsInRoot(): void
    {
        // realpath() may resolve macOS' /tmp to /private/tmp, so use the
        // same resolved root the validator normalises to.
        $root = realpath(sys_get_temp_dir());
        $validator = new PathValidator($root, checkSymlinks: false);
        $file = $root . '/some-virtual.txt';

        // File doesn't need to exist; the symlink-skip branch just
        // compares normalised prefixes.
        self::assertSame($file, $validator->validateFilePath($file));
    }

    #[Test]
    public function validateFilePathWithoutSymlinkCheckRejectsOutsideRoot(): void
    {
        $validator = new PathValidator(sys_get_temp_dir(), checkSymlinks: false);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Path outside web root');

        $validator->validateFilePath('/etc/passwd');
    }

    #[Test]
    public function validateFilePathAcceptsNonExistentFileInsideWebRoot(): void
    {
        $tmpDir = sys_get_temp_dir();
        // realpath() returns false for a non-existent file, so the handler
        // takes the dirname fallback and confirms the parent dir is inside
        // the web root.
        $virtual = $tmpDir . '/wppack-pathvalidator-' . uniqid() . '.txt';

        self::assertSame($virtual, $this->validator->validateFilePath($virtual));
    }

    #[Test]
    public function validateFilePathRejectsNonExistentFileOutsideWebRoot(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Path outside web root');

        $this->validator->validateFilePath('/nonexistent/elsewhere/' . uniqid() . '.txt');
    }

    #[Test]
    public function validateFilePathAcceptsSymlinkInWebRootEvenWhenTargetIsOutside(): void
    {
        // Nest webRoot inside tmp and point a symlink to a file that
        // lives OUTSIDE webRoot. realpath() resolves the symlink to its
        // target (outside root), so the straight str_starts_with on line
        // 88 fails and the validator falls through to the normalised
        // entry-path check on lines 94-97.
        $baseTmp = realpath(sys_get_temp_dir());
        $webRoot = $baseTmp . '/wppack-webroot-' . uniqid();
        mkdir($webRoot);

        $targetFile = $baseTmp . '/wppack-target-' . uniqid() . '.txt';
        file_put_contents($targetFile, 'x');

        $linkInsideRoot = $webRoot . '/link.txt';
        symlink($targetFile, $linkInsideRoot);

        try {
            $validator = new PathValidator($webRoot);
            $result = $validator->validateFilePath($linkInsideRoot);

            // The normalised-entry-path branch returns the original
            // symlink path as-is.
            self::assertSame($linkInsideRoot, $result);
        } finally {
            @unlink($linkInsideRoot);
            @rmdir($webRoot);
            @unlink($targetFile);
        }
    }

    #[Test]
    public function validateFilePathRejectsSymlinkWhoseEntryAndTargetAreBothOutsideRoot(): void
    {
        $baseTmp = realpath(sys_get_temp_dir());
        $webRoot = $baseTmp . '/wppack-webroot-' . uniqid();
        mkdir($webRoot);

        // Symlink and target both outside webRoot → every branch rejects.
        $targetFile = $baseTmp . '/wppack-out-target-' . uniqid() . '.txt';
        file_put_contents($targetFile, 'x');
        $linkOutsideRoot = $baseTmp . '/wppack-out-link-' . uniqid() . '.txt';
        symlink($targetFile, $linkOutsideRoot);

        $validator = new PathValidator($webRoot);

        try {
            $this->expectException(SecurityException::class);
            $this->expectExceptionMessage('Path outside web root');

            $validator->validateFilePath($linkOutsideRoot);
        } finally {
            @unlink($linkOutsideRoot);
            @rmdir($webRoot);
            @unlink($targetFile);
        }
    }
}
