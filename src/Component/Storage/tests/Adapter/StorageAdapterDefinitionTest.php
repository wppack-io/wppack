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

namespace WPPack\Component\Storage\Tests\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Storage\Adapter\StorageAdapterDefinition;
use WPPack\Component\Storage\Adapter\StorageAdapterField;

#[CoversClass(StorageAdapterDefinition::class)]
final class StorageAdapterDefinitionTest extends TestCase
{
    #[Test]
    public function buildDsnWithHostAndPath(): void
    {
        $def = new StorageAdapterDefinition('s3', 'S3', [
            new StorageAdapterField('bucket', 'Bucket', dsnPart: 'host'),
            new StorageAdapterField('region', 'Region', dsnPart: 'option:region'),
        ]);
        self::assertSame('s3://my-bucket?region=us-east-1', $def->buildDsn(['bucket' => 'my-bucket', 'region' => 'us-east-1']));
    }

    #[Test]
    public function buildDsnWithUserAndPassword(): void
    {
        $def = new StorageAdapterDefinition('s3', 'S3', [
            new StorageAdapterField('key', 'Key', dsnPart: 'user'),
            new StorageAdapterField('secret', 'Secret', type: 'password', dsnPart: 'password'),
            new StorageAdapterField('bucket', 'Bucket', dsnPart: 'host'),
        ]);
        self::assertSame('s3://AKID:secret@my-bucket', $def->buildDsn(['key' => 'AKID', 'secret' => 'secret', 'bucket' => 'my-bucket']));
    }

    #[Test]
    public function buildDsnWithPath(): void
    {
        $def = new StorageAdapterDefinition('local', 'Local', [
            new StorageAdapterField('path', 'Path', dsnPart: 'path'),
        ]);
        self::assertSame('local://default/var/uploads', $def->buildDsn(['path' => 'var/uploads']));
    }

    #[Test]
    public function buildDsnSkipsEmptyFields(): void
    {
        $def = new StorageAdapterDefinition('gcs', 'GCS', [
            new StorageAdapterField('bucket', 'Bucket', dsnPart: 'host'),
            new StorageAdapterField('project', 'Project', dsnPart: 'option:project'),
        ]);
        self::assertSame('gcs://my-bucket', $def->buildDsn(['bucket' => 'my-bucket', 'project' => '']));
    }

    #[Test]
    public function schemeAndLabelProperties(): void
    {
        $def = new StorageAdapterDefinition('azure', 'Azure Blob', capabilities: ['presignedUrl']);
        self::assertSame('azure', $def->scheme);
        self::assertSame('Azure Blob', $def->label);
        self::assertSame(['presignedUrl'], $def->capabilities);
    }
}
