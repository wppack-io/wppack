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

namespace WpPack\Component\Hook\Tests\Attribute\Nonce;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\Nonce\Filter\NonceLifeFilter;
use WpPack\Component\Hook\Attribute\Nonce\Filter\NonceUserLoggedOutFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function nonceLifeFilterHasCorrectHookName(): void
    {
        $filter = new NonceLifeFilter();

        self::assertSame('nonce_life', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function nonceUserLoggedOutFilterHasCorrectHookName(): void
    {
        $filter = new NonceUserLoggedOutFilter();

        self::assertSame('nonce_user_logged_out', $filter->hook);
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new NonceLifeFilter());
        self::assertInstanceOf(Filter::class, new NonceUserLoggedOutFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[NonceLifeFilter(priority: 5)]
            public function onNonceLife(): void {}
        };

        $filterMethod = new \ReflectionMethod($class, 'onNonceLife');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('nonce_life', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
