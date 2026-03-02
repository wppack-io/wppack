<?php

declare(strict_types=1);

namespace WpPack\Component\SiteHealth\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\SiteHealth\Attribute\Action\SiteHealthCheckCompleteAction;
use WpPack\Component\SiteHealth\Attribute\Action\SiteHealthScheduledCheckAction;
use WpPack\Component\SiteHealth\Attribute\Filter\PreSiteHealthCheckFilter;
use WpPack\Component\SiteHealth\Attribute\Filter\SiteHealthCronScheduleFilter;
use WpPack\Component\SiteHealth\Attribute\Filter\SiteHealthDebugInfoFilter;
use WpPack\Component\SiteHealth\Attribute\Filter\SiteHealthHttpsStatusTestFilter;
use WpPack\Component\SiteHealth\Attribute\Filter\SiteHealthNavigationTabsFilter;
use WpPack\Component\SiteHealth\Attribute\Filter\SiteHealthPhpVersionTestFilter;
use WpPack\Component\SiteHealth\Attribute\Filter\SiteHealthSqlServerTestFilter;
use WpPack\Component\SiteHealth\Attribute\Filter\SiteHealthStatusFilter;
use WpPack\Component\SiteHealth\Attribute\Filter\SiteHealthTestsFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function siteHealthCheckCompleteActionHasCorrectHookName(): void
    {
        $action = new SiteHealthCheckCompleteAction();

        self::assertSame('site_health_check_complete', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function siteHealthCheckCompleteActionAcceptsCustomPriority(): void
    {
        $action = new SiteHealthCheckCompleteAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function siteHealthScheduledCheckActionHasCorrectHookName(): void
    {
        $action = new SiteHealthScheduledCheckAction();

        self::assertSame('site_health_scheduled_check', $action->hook);
    }

    #[Test]
    public function preSiteHealthCheckFilterHasCorrectHookName(): void
    {
        $filter = new PreSiteHealthCheckFilter();

        self::assertSame('pre_site_health_check', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function siteHealthCronScheduleFilterHasCorrectHookName(): void
    {
        $filter = new SiteHealthCronScheduleFilter();

        self::assertSame('site_health_cron_schedule', $filter->hook);
    }

    #[Test]
    public function siteHealthDebugInfoFilterHasCorrectHookName(): void
    {
        $filter = new SiteHealthDebugInfoFilter();

        self::assertSame('site_health_debug_info', $filter->hook);
    }

    #[Test]
    public function siteHealthHttpsStatusTestFilterHasCorrectHookName(): void
    {
        $filter = new SiteHealthHttpsStatusTestFilter();

        self::assertSame('site_health_https_status_test', $filter->hook);
    }

    #[Test]
    public function siteHealthNavigationTabsFilterHasCorrectHookName(): void
    {
        $filter = new SiteHealthNavigationTabsFilter();

        self::assertSame('site_health_navigation_tabs', $filter->hook);
    }

    #[Test]
    public function siteHealthPhpVersionTestFilterHasCorrectHookName(): void
    {
        $filter = new SiteHealthPhpVersionTestFilter();

        self::assertSame('site_health_php_version_test', $filter->hook);
    }

    #[Test]
    public function siteHealthSqlServerTestFilterHasCorrectHookName(): void
    {
        $filter = new SiteHealthSqlServerTestFilter();

        self::assertSame('site_health_sql_server_test', $filter->hook);
    }

    #[Test]
    public function siteHealthStatusFilterHasCorrectHookName(): void
    {
        $filter = new SiteHealthStatusFilter();

        self::assertSame('site_health_status', $filter->hook);
    }

    #[Test]
    public function siteHealthTestsFilterHasCorrectHookName(): void
    {
        $filter = new SiteHealthTestsFilter();

        self::assertSame('site_health_tests', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new SiteHealthCheckCompleteAction());
        self::assertInstanceOf(Action::class, new SiteHealthScheduledCheckAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new PreSiteHealthCheckFilter());
        self::assertInstanceOf(Filter::class, new SiteHealthCronScheduleFilter());
        self::assertInstanceOf(Filter::class, new SiteHealthDebugInfoFilter());
        self::assertInstanceOf(Filter::class, new SiteHealthHttpsStatusTestFilter());
        self::assertInstanceOf(Filter::class, new SiteHealthNavigationTabsFilter());
        self::assertInstanceOf(Filter::class, new SiteHealthPhpVersionTestFilter());
        self::assertInstanceOf(Filter::class, new SiteHealthSqlServerTestFilter());
        self::assertInstanceOf(Filter::class, new SiteHealthStatusFilter());
        self::assertInstanceOf(Filter::class, new SiteHealthTestsFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[SiteHealthCheckCompleteAction]
            public function onCheckComplete(): void {}

            #[SiteHealthTestsFilter(priority: 5)]
            public function onTests(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onCheckComplete');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('site_health_check_complete', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onTests');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('site_health_tests', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
