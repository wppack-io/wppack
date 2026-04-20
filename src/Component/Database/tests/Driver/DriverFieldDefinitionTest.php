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

namespace WPPack\Component\Database\Tests\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Driver\DriverDefinition;
use WPPack\Component\Database\Driver\DriverField;

#[CoversClass(DriverField::class)]
#[CoversClass(DriverDefinition::class)]
final class DriverFieldDefinitionTest extends TestCase
{
    #[Test]
    public function driverFieldStoresAllProperties(): void
    {
        $field = new DriverField(
            name: 'host',
            label: 'Host',
            type: 'text',
            required: true,
            default: 'localhost',
            dsnPart: 'host',
        );

        self::assertSame('host', $field->name);
        self::assertSame('Host', $field->label);
        self::assertSame('text', $field->type);
        self::assertTrue($field->required);
        self::assertSame('localhost', $field->default);
        self::assertSame('host', $field->dsnPart);
    }

    #[Test]
    public function driverFieldDefaults(): void
    {
        $field = new DriverField(name: 'user', label: 'User');

        self::assertSame('text', $field->type);
        self::assertFalse($field->required);
        self::assertNull($field->default);
        self::assertNull($field->dsnPart);
    }

    #[Test]
    public function driverDefinitionStoresSchemeLabelAndFields(): void
    {
        $fields = [
            new DriverField('host', 'Host', dsnPart: 'host'),
            new DriverField('port', 'Port', dsnPart: 'port', default: '3306'),
        ];

        $def = new DriverDefinition(scheme: 'mysql', label: 'MySQL', fields: $fields);

        self::assertSame('mysql', $def->scheme);
        self::assertSame('MySQL', $def->label);
        self::assertSame($fields, $def->fields);
        self::assertCount(2, $def->fields);
    }

    #[Test]
    public function driverDefinitionDefaultsToEmptyFields(): void
    {
        $def = new DriverDefinition(scheme: 'sqlite', label: 'SQLite');

        self::assertSame([], $def->fields);
    }
}
