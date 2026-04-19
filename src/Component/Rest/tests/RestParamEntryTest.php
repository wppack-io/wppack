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

namespace WPPack\Component\Rest\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Rest\Attribute\Param;
use WPPack\Component\Rest\RestParamEntry;

final class RestParamEntryTest extends TestCase
{
    #[Test]
    public function storesAllProperties(): void
    {
        $param = new Param(description: 'Test');
        $entry = new RestParamEntry('per_page', 'integer', false, 10, $param);

        self::assertSame('per_page', $entry->name);
        self::assertSame('integer', $entry->type);
        self::assertFalse($entry->required);
        self::assertSame(10, $entry->default);
        self::assertSame($param, $entry->param);
    }

    #[Test]
    public function toArgsReturnsMinimalArray(): void
    {
        $entry = new RestParamEntry('id', 'integer', true, null, null);

        $args = $entry->toArgs();

        self::assertSame('integer', $args['type']);
        self::assertTrue($args['required']);
        self::assertArrayNotHasKey('default', $args);
    }

    #[Test]
    public function toArgsIncludesRequiredAndDefault(): void
    {
        $entry = new RestParamEntry('status', 'string', false, 'publish', null);

        $args = $entry->toArgs();

        self::assertFalse($args['required']);
        self::assertSame('publish', $args['default']);
    }

    #[Test]
    public function toArgsIncludesParamConstraints(): void
    {
        $param = new Param(
            description: 'Items per page',
            minimum: 1,
            maximum: 100,
            minLength: 3,
            maxLength: 50,
            pattern: '^\d+$',
            format: 'uri',
        );
        $entry = new RestParamEntry('per_page', 'integer', false, 10, $param);

        $args = $entry->toArgs();

        self::assertSame('Items per page', $args['description']);
        self::assertSame(1, $args['minimum']);
        self::assertSame(100, $args['maximum']);
        self::assertSame(3, $args['minLength']);
        self::assertSame(50, $args['maxLength']);
        self::assertSame('^\d+$', $args['pattern']);
        self::assertSame('uri', $args['format']);
    }

    #[Test]
    public function toArgsConvertsItemsToNestedArray(): void
    {
        $param = new Param(items: 'integer');
        $entry = new RestParamEntry('ids', 'array', true, null, $param);

        $args = $entry->toArgs();

        self::assertSame(['type' => 'integer'], $args['items']);
    }

    #[Test]
    public function toArgsExcludesNullValues(): void
    {
        $param = new Param(minimum: 1);
        $entry = new RestParamEntry('count', 'integer', true, null, $param);

        $args = $entry->toArgs();

        self::assertArrayNotHasKey('maximum', $args);
        self::assertArrayNotHasKey('description', $args);
        self::assertArrayNotHasKey('enum', $args);
        self::assertArrayNotHasKey('pattern', $args);
        self::assertArrayNotHasKey('format', $args);
        self::assertArrayNotHasKey('items', $args);
    }

    #[Test]
    public function toArgsExcludesCallbackProperties(): void
    {
        $param = new Param(validate: 'validateId', sanitize: 'sanitizeId');
        $entry = new RestParamEntry('id', 'integer', true, null, $param);

        $args = $entry->toArgs();

        self::assertArrayNotHasKey('validate', $args);
        self::assertArrayNotHasKey('sanitize', $args);
        self::assertArrayNotHasKey('validate_callback', $args);
        self::assertArrayNotHasKey('sanitize_callback', $args);
    }

    #[Test]
    public function toArgsOmitsDefaultWhenRequired(): void
    {
        $entry = new RestParamEntry('id', 'integer', true, null, null);

        $args = $entry->toArgs();

        self::assertTrue($args['required']);
        self::assertArrayNotHasKey('default', $args);
    }

    #[Test]
    public function toArgsIncludesEnumValues(): void
    {
        $param = new Param(enum: ['draft', 'publish', 'pending']);
        $entry = new RestParamEntry('status', 'string', true, null, $param);

        $args = $entry->toArgs();

        self::assertSame(['draft', 'publish', 'pending'], $args['enum']);
    }

    #[Test]
    public function toArgsOmitsDefaultWhenNull(): void
    {
        $entry = new RestParamEntry('filter', 'string', false, null, null);

        $args = $entry->toArgs();

        self::assertFalse($args['required']);
        self::assertArrayNotHasKey('default', $args);
    }
}
