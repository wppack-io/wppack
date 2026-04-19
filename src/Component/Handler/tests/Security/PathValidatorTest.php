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
}
