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

namespace WpPack\Component\Logger\Tests\Context;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Logger\Context\LoggerContext;

final class LoggerContextTest extends TestCase
{
    #[Test]
    public function allReturnsContext(): void
    {
        $data = ['key' => 'value', 'number' => 42];
        $context = new LoggerContext($data);

        self::assertSame($data, $context->all());
    }

    #[Test]
    public function emptyContext(): void
    {
        $context = new LoggerContext([]);

        self::assertSame([], $context->all());
    }

    #[Test]
    public function contextIsImmutable(): void
    {
        $data = ['key' => 'value'];
        $context = new LoggerContext($data);

        $result = $context->all();
        $result['key'] = 'modified';

        self::assertSame('value', $context->all()['key']);
    }
}
