<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Tests\Message;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Plugin\S3StoragePlugin\Message\S3EventNormalizer;
use WpPack\Plugin\S3StoragePlugin\Message\S3ObjectCreatedMessage;

final class S3EventNormalizerTest extends TestCase
{
    private S3EventNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new S3EventNormalizer();
    }

    #[Test]
    public function normalizeValidS3Event(): void
    {
        $event = [
            'Records' => [
                [
                    'eventName' => 's3:ObjectCreated:Put',
                    's3' => [
                        'bucket' => ['name' => 'my-bucket'],
                        'object' => [
                            'key' => 'uploads/2024/01/photo.jpg',
                            'size' => 12345,
                            'eTag' => 'abc123',
                        ],
                    ],
                ],
            ],
        ];

        $messages = $this->normalizer->normalize($event);

        self::assertCount(1, $messages);
        self::assertInstanceOf(S3ObjectCreatedMessage::class, $messages[0]);
        self::assertSame('my-bucket', $messages[0]->bucket);
        self::assertSame('uploads/2024/01/photo.jpg', $messages[0]->key);
        self::assertSame(12345, $messages[0]->size);
        self::assertSame('abc123', $messages[0]->eTag);
    }

    #[Test]
    public function normalizeMultipleRecords(): void
    {
        $event = [
            'Records' => [
                [
                    'eventName' => 's3:ObjectCreated:Put',
                    's3' => [
                        'bucket' => ['name' => 'my-bucket'],
                        'object' => [
                            'key' => 'uploads/file1.jpg',
                            'size' => 100,
                            'eTag' => 'etag1',
                        ],
                    ],
                ],
                [
                    'eventName' => 's3:ObjectCreated:CompleteMultipartUpload',
                    's3' => [
                        'bucket' => ['name' => 'my-bucket'],
                        'object' => [
                            'key' => 'uploads/file2.pdf',
                            'size' => 200,
                            'eTag' => 'etag2',
                        ],
                    ],
                ],
            ],
        ];

        $messages = $this->normalizer->normalize($event);

        self::assertCount(2, $messages);
        self::assertSame('uploads/file1.jpg', $messages[0]->key);
        self::assertSame('uploads/file2.pdf', $messages[1]->key);
    }

    #[Test]
    public function normalizeUrlDecodesKeys(): void
    {
        $event = [
            'Records' => [
                [
                    'eventName' => 's3:ObjectCreated:Put',
                    's3' => [
                        'bucket' => ['name' => 'my-bucket'],
                        'object' => [
                            'key' => 'uploads/2024/01/my+photo+%28copy%29.jpg',
                            'size' => 500,
                            'eTag' => 'etag1',
                        ],
                    ],
                ],
            ],
        ];

        $messages = $this->normalizer->normalize($event);

        self::assertCount(1, $messages);
        self::assertSame('uploads/2024/01/my photo (copy).jpg', $messages[0]->key);
    }

    #[Test]
    public function normalizeEmptyRecords(): void
    {
        $event = ['Records' => []];

        $messages = $this->normalizer->normalize($event);

        self::assertSame([], $messages);
    }

    #[Test]
    public function normalizeMissingRecordsKey(): void
    {
        $event = [];

        $messages = $this->normalizer->normalize($event);

        self::assertSame([], $messages);
    }

    #[Test]
    public function normalizeFiltersNonObjectCreatedEvents(): void
    {
        $event = [
            'Records' => [
                [
                    'eventName' => 's3:ObjectRemoved:Delete',
                    's3' => [
                        'bucket' => ['name' => 'my-bucket'],
                        'object' => [
                            'key' => 'uploads/deleted.jpg',
                            'size' => 100,
                            'eTag' => 'etag1',
                        ],
                    ],
                ],
                [
                    'eventName' => 's3:ObjectCreated:Put',
                    's3' => [
                        'bucket' => ['name' => 'my-bucket'],
                        'object' => [
                            'key' => 'uploads/created.jpg',
                            'size' => 200,
                            'eTag' => 'etag2',
                        ],
                    ],
                ],
                [
                    'eventName' => 's3:Replication:OperationCompletedReplication',
                    's3' => [
                        'bucket' => ['name' => 'my-bucket'],
                        'object' => [
                            'key' => 'uploads/replicated.jpg',
                            'size' => 300,
                            'eTag' => 'etag3',
                        ],
                    ],
                ],
            ],
        ];

        $messages = $this->normalizer->normalize($event);

        self::assertCount(1, $messages);
        self::assertSame('uploads/created.jpg', $messages[0]->key);
    }

    #[Test]
    public function normalizeSkipsRecordsWithEmptyBucketOrKey(): void
    {
        $event = [
            'Records' => [
                [
                    'eventName' => 's3:ObjectCreated:Put',
                    's3' => [
                        'bucket' => ['name' => ''],
                        'object' => [
                            'key' => 'uploads/file.jpg',
                            'size' => 100,
                            'eTag' => 'etag1',
                        ],
                    ],
                ],
                [
                    'eventName' => 's3:ObjectCreated:Put',
                    's3' => [
                        'bucket' => ['name' => 'my-bucket'],
                        'object' => [
                            'key' => '',
                            'size' => 200,
                            'eTag' => 'etag2',
                        ],
                    ],
                ],
            ],
        ];

        $messages = $this->normalizer->normalize($event);

        self::assertSame([], $messages);
    }

    #[Test]
    public function normalizeHandlesAllObjectCreatedSubtypes(): void
    {
        $event = [
            'Records' => [
                [
                    'eventName' => 's3:ObjectCreated:Put',
                    's3' => [
                        'bucket' => ['name' => 'b'],
                        'object' => ['key' => 'a.jpg', 'size' => 1, 'eTag' => 'e'],
                    ],
                ],
                [
                    'eventName' => 's3:ObjectCreated:Post',
                    's3' => [
                        'bucket' => ['name' => 'b'],
                        'object' => ['key' => 'b.jpg', 'size' => 1, 'eTag' => 'e'],
                    ],
                ],
                [
                    'eventName' => 's3:ObjectCreated:Copy',
                    's3' => [
                        'bucket' => ['name' => 'b'],
                        'object' => ['key' => 'c.jpg', 'size' => 1, 'eTag' => 'e'],
                    ],
                ],
                [
                    'eventName' => 's3:ObjectCreated:CompleteMultipartUpload',
                    's3' => [
                        'bucket' => ['name' => 'b'],
                        'object' => ['key' => 'd.jpg', 'size' => 1, 'eTag' => 'e'],
                    ],
                ],
            ],
        ];

        $messages = $this->normalizer->normalize($event);

        self::assertCount(4, $messages);
    }
}
