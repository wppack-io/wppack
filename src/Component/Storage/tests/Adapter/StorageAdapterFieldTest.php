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
use WPPack\Component\Storage\Adapter\StorageAdapterField;

#[CoversClass(StorageAdapterField::class)]
final class StorageAdapterFieldTest extends TestCase
{
    #[Test]
    public function storesAllProperties(): void
    {
        $field = new StorageAdapterField(
            name: 'accessKey',
            label: 'Access Key',
            type: 'password',
            required: true,
            default: null,
            help: 'S3 access key ID',
            dsnPart: 'user',
            options: [['label' => 'us-east-1', 'value' => 'us-east-1']],
            maxWidth: '300px',
            conditional: 'region != ""',
        );

        self::assertSame('accessKey', $field->name);
        self::assertSame('Access Key', $field->label);
        self::assertSame('password', $field->type);
        self::assertTrue($field->required);
        self::assertNull($field->default);
        self::assertSame('S3 access key ID', $field->help);
        self::assertSame('user', $field->dsnPart);
        self::assertSame([['label' => 'us-east-1', 'value' => 'us-east-1']], $field->options);
        self::assertSame('300px', $field->maxWidth);
        self::assertSame('region != ""', $field->conditional);
    }

    #[Test]
    public function defaults(): void
    {
        $field = new StorageAdapterField(name: 'bucket', label: 'Bucket');

        self::assertSame('text', $field->type);
        self::assertFalse($field->required);
        self::assertNull($field->default);
        self::assertNull($field->help);
        self::assertNull($field->dsnPart);
        self::assertNull($field->options);
        self::assertNull($field->maxWidth);
        self::assertNull($field->conditional);
    }
}
