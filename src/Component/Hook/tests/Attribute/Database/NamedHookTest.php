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

namespace WpPack\Component\Hook\Tests\Attribute\Database;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\Database\Action\WpUpgradeAction;
use WpPack\Component\Hook\Attribute\Database\Filter\DbDeltaCreateQueriesFilter;
use WpPack\Component\Hook\Attribute\Database\Filter\DbDeltaInsertQueriesFilter;
use WpPack\Component\Hook\Attribute\Database\Filter\DbDeltaQueriesFilter;
use WpPack\Component\Hook\Attribute\Database\Filter\DbprepareFilter;
use WpPack\Component\Hook\Attribute\Database\Filter\QueryFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function wpUpgradeActionHasCorrectHookName(): void
    {
        $action = new WpUpgradeAction();

        self::assertSame('wp_upgrade', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function wpUpgradeActionAcceptsCustomPriority(): void
    {
        $action = new WpUpgradeAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function dbDeltaCreateQueriesFilterHasCorrectHookName(): void
    {
        $filter = new DbDeltaCreateQueriesFilter();

        self::assertSame('dbdelta_create_queries', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function dbDeltaInsertQueriesFilterHasCorrectHookName(): void
    {
        $filter = new DbDeltaInsertQueriesFilter();

        self::assertSame('dbdelta_insert_queries', $filter->hook);
    }

    #[Test]
    public function dbDeltaQueriesFilterHasCorrectHookName(): void
    {
        $filter = new DbDeltaQueriesFilter();

        self::assertSame('dbdelta_queries', $filter->hook);
    }

    #[Test]
    public function dbprepareFilterHasCorrectHookName(): void
    {
        $filter = new DbprepareFilter();

        self::assertSame('dbprepare', $filter->hook);
    }

    #[Test]
    public function queryFilterHasCorrectHookName(): void
    {
        $filter = new QueryFilter();

        self::assertSame('query', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new WpUpgradeAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new DbDeltaCreateQueriesFilter());
        self::assertInstanceOf(Filter::class, new DbDeltaInsertQueriesFilter());
        self::assertInstanceOf(Filter::class, new DbDeltaQueriesFilter());
        self::assertInstanceOf(Filter::class, new DbprepareFilter());
        self::assertInstanceOf(Filter::class, new QueryFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[WpUpgradeAction]
            public function onWpUpgrade(): void {}

            #[QueryFilter(priority: 5)]
            public function onQuery(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onWpUpgrade');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('wp_upgrade', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onQuery');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('query', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
