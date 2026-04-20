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

namespace WPPack\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Mailer\Transport\TransportField;

#[CoversClass(TransportField::class)]
final class TransportFieldTest extends TestCase
{
    #[Test]
    public function storesAllProperties(): void
    {
        $field = new TransportField(
            name: 'region',
            label: 'AWS Region',
            type: 'select',
            required: true,
            default: 'us-east-1',
            help: 'SES region',
            dsnPart: 'option:region',
            options: [['label' => 'us-east-1', 'value' => 'us-east-1']],
            maxWidth: '200px',
        );

        self::assertSame('region', $field->name);
        self::assertSame('AWS Region', $field->label);
        self::assertSame('select', $field->type);
        self::assertTrue($field->required);
        self::assertSame('us-east-1', $field->default);
        self::assertSame('SES region', $field->help);
        self::assertSame('option:region', $field->dsnPart);
        self::assertSame([['label' => 'us-east-1', 'value' => 'us-east-1']], $field->options);
        self::assertSame('200px', $field->maxWidth);
    }

    #[Test]
    public function defaults(): void
    {
        $field = new TransportField(name: 'host', label: 'Host');

        self::assertSame('text', $field->type);
        self::assertFalse($field->required);
        self::assertNull($field->default);
        self::assertNull($field->help);
        self::assertNull($field->dsnPart);
        self::assertNull($field->options);
        self::assertNull($field->maxWidth);
    }
}
