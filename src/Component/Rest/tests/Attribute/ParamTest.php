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

namespace WpPack\Component\Rest\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\Attribute\Param;

final class ParamTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $param = new Param();

        self::assertNull($param->description);
        self::assertNull($param->enum);
        self::assertNull($param->minimum);
        self::assertNull($param->maximum);
        self::assertNull($param->minLength);
        self::assertNull($param->maxLength);
        self::assertNull($param->pattern);
        self::assertNull($param->format);
        self::assertNull($param->items);
        self::assertNull($param->validate);
        self::assertNull($param->sanitize);
    }

    #[Test]
    public function allParametersCustomized(): void
    {
        $param = new Param(
            description: 'A test param',
            enum: ['a', 'b', 'c'],
            minimum: 1,
            maximum: 100,
            minLength: 3,
            maxLength: 50,
            pattern: '^[a-z]+$',
            format: 'email',
            items: 'string',
            validate: 'validateParam',
            sanitize: 'sanitizeParam',
        );

        self::assertSame('A test param', $param->description);
        self::assertSame(['a', 'b', 'c'], $param->enum);
        self::assertSame(1, $param->minimum);
        self::assertSame(100, $param->maximum);
        self::assertSame(3, $param->minLength);
        self::assertSame(50, $param->maxLength);
        self::assertSame('^[a-z]+$', $param->pattern);
        self::assertSame('email', $param->format);
        self::assertSame('string', $param->items);
        self::assertSame('validateParam', $param->validate);
        self::assertSame('sanitizeParam', $param->sanitize);
    }

    #[Test]
    public function enumValues(): void
    {
        $param = new Param(enum: ['publish', 'draft', 'pending']);

        self::assertSame(['publish', 'draft', 'pending'], $param->enum);
    }

    #[Test]
    public function validationConstraints(): void
    {
        $param = new Param(minimum: 0, maximum: 999, minLength: 1, maxLength: 255, pattern: '^\d+$');

        self::assertSame(0, $param->minimum);
        self::assertSame(999, $param->maximum);
        self::assertSame(1, $param->minLength);
        self::assertSame(255, $param->maxLength);
        self::assertSame('^\d+$', $param->pattern);
    }

    #[Test]
    public function targetsParameterOnly(): void
    {
        $reflection = new \ReflectionClass(Param::class);
        $attributes = $reflection->getAttributes(\Attribute::class);
        $flags = $attributes[0]->newInstance()->flags;

        self::assertSame(\Attribute::TARGET_PARAMETER, $flags);
    }

    #[Test]
    public function isNotRepeatable(): void
    {
        $reflection = new \ReflectionClass(Param::class);
        $attributes = $reflection->getAttributes(\Attribute::class);
        $flags = $attributes[0]->newInstance()->flags;

        self::assertSame(0, $flags & \Attribute::IS_REPEATABLE);
    }
}
