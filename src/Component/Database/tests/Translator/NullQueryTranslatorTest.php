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

namespace WpPack\Component\Database\Tests\Translator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Translator\NullQueryTranslator;

final class NullQueryTranslatorTest extends TestCase
{
    #[Test]
    public function passesThrough(): void
    {
        $translator = new NullQueryTranslator();

        self::assertSame(['SELECT 1'], $translator->translate('SELECT 1'));
    }

    #[Test]
    public function passesThroughComplexQuery(): void
    {
        $sql = "INSERT INTO `wp_posts` VALUES (1, 'title', 'content')";
        $translator = new NullQueryTranslator();

        self::assertSame([$sql], $translator->translate($sql));
    }
}
