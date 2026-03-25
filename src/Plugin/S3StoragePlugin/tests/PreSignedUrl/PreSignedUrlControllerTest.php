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

namespace WpPack\Plugin\S3StoragePlugin\Tests\PreSignedUrl;

use AsyncAws\S3\S3Client;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Storage\Bridge\S3\S3StorageAdapter;
use WpPack\Plugin\S3StoragePlugin\PreSignedUrl\PreSignedUrlController;
use WpPack\Plugin\S3StoragePlugin\PreSignedUrl\PreSignedUrlGenerator;
use WpPack\Plugin\S3StoragePlugin\PreSignedUrl\UploadPolicy;

final class PreSignedUrlControllerTest extends TestCase
{
    private function createController(
        ?PreSignedUrlGenerator $generator = null,
        ?UploadPolicy $policy = null,
    ): PreSignedUrlController {
        $generator ??= new PreSignedUrlGenerator(
            new S3StorageAdapter(
                new S3Client(['region' => 'us-east-1']),
                'test-bucket',
                'uploads',
            ),
        );
        $policy ??= new UploadPolicy(allowedMimeTypes: []);

        return new PreSignedUrlController($generator, $policy);
    }

    #[Test]
    public function returnsPresignedUrlForValidRequest(): void
    {
        $controller = $this->createController();

        $request = new Request(
            content: '{"filename":"test.jpg","content_type":"image/jpeg","content_length":"1024"}',
        );

        $response = $controller->__invoke($request);

        self::assertSame(200, $response->statusCode);
    }

    #[Test]
    public function returnsErrorWhenFilenameIsMissing(): void
    {
        $controller = $this->createController();

        $request = new Request(
            content: '{"content_type":"image/jpeg","content_length":"1024"}',
        );

        $response = $controller->__invoke($request);

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function returnsErrorWhenContentTypeIsMissing(): void
    {
        $controller = $this->createController();

        $request = new Request(
            content: '{"filename":"test.jpg","content_length":"1024"}',
        );

        $response = $controller->__invoke($request);

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function returnsErrorWhenContentLengthIsMissing(): void
    {
        $controller = $this->createController();

        $request = new Request(
            content: '{"filename":"test.jpg","content_type":"image/jpeg"}',
        );

        $response = $controller->__invoke($request);

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function returnsErrorWhenContentTypeNotAllowed(): void
    {
        $policy = new UploadPolicy(allowedMimeTypes: ['image/jpeg']);
        $controller = $this->createController(policy: $policy);

        $request = new Request(
            content: '{"filename":"test.html","content_type":"text/html","content_length":"1024"}',
        );

        $response = $controller->__invoke($request);

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function returnsErrorWhenContentLengthExceedsMax(): void
    {
        $policy = new UploadPolicy(maxFileSize: 100, allowedMimeTypes: []);
        $controller = $this->createController(policy: $policy);

        $request = new Request(
            content: '{"filename":"test.jpg","content_type":"image/jpeg","content_length":"200"}',
        );

        $response = $controller->__invoke($request);

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function worksWithPostData(): void
    {
        $controller = $this->createController();

        $request = new Request(
            post: ['filename' => 'test.jpg', 'content_type' => 'image/jpeg', 'content_length' => '1024'],
        );

        $response = $controller->__invoke($request);

        self::assertSame(200, $response->statusCode);
    }
}
