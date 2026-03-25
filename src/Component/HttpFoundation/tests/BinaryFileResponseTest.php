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

namespace WpPack\Component\HttpFoundation\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\BinaryFileResponse;
use WpPack\Component\HttpFoundation\Response;

final class BinaryFileResponseTest extends TestCase
{
    #[Test]
    public function storesPathFilenameAndDisposition(): void
    {
        $response = new BinaryFileResponse('/var/files/report.pdf', 'download.pdf', 'inline');

        self::assertSame('/var/files/report.pdf', $response->path);
        self::assertSame('download.pdf', $response->filename);
        self::assertSame('inline', $response->disposition);
    }

    #[Test]
    public function defaultValues(): void
    {
        $response = new BinaryFileResponse('/var/files/report.pdf');

        self::assertNull($response->filename);
        self::assertSame('attachment', $response->disposition);
        self::assertSame(200, $response->statusCode);
    }

    #[Test]
    public function customStatusCode(): void
    {
        $response = new BinaryFileResponse('/var/files/report.pdf', statusCode: 206);

        self::assertSame(206, $response->statusCode);
    }

    #[Test]
    public function extendsResponse(): void
    {
        $response = new BinaryFileResponse('/var/files/report.pdf');

        self::assertInstanceOf(Response::class, $response);
    }

    #[Test]
    public function sendContentOutputsFileContent(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wppack_binary_test_');
        file_put_contents($path, 'file content here');

        try {
            $response = new BinaryFileResponse($path);

            ob_start();
            $response->send();
            $output = ob_get_clean();

            self::assertSame('file content here', $output);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function sendContentThrowsForMissingFile(): void
    {
        $response = new BinaryFileResponse('/nonexistent/file.bin');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File "/nonexistent/file.bin" does not exist.');

        $response->send();
    }
}
