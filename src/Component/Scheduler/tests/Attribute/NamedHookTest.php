<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Scheduler\Attribute\Action\ScheduledEventAction;
use WpPack\Component\Scheduler\Attribute\Action\WpCronAction;
use WpPack\Component\Scheduler\Attribute\Filter\CronSchedulesFilter;
use WpPack\Component\Scheduler\Attribute\Filter\GetScheduleFilter;
use WpPack\Component\Scheduler\Attribute\Filter\PreDoEventFilter;
use WpPack\Component\Scheduler\Attribute\Filter\PreScheduleEventFilter;
use WpPack\Component\Scheduler\Attribute\Filter\PreUnscheduleEventFilter;
use WpPack\Component\Scheduler\Attribute\Filter\ScheduleEventFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function wpCronActionHasCorrectHookName(): void
    {
        $action = new WpCronAction();

        self::assertSame('wp_cron', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function wpCronActionAcceptsCustomPriority(): void
    {
        $action = new WpCronAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function scheduledEventActionUsesDynamicHookName(): void
    {
        $action = new ScheduledEventAction(event: 'my_custom_cron');

        self::assertSame('my_custom_cron', $action->hook);
        self::assertSame('my_custom_cron', $action->event);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function scheduledEventActionAcceptsCustomPriority(): void
    {
        $action = new ScheduledEventAction(event: 'daily_cleanup', priority: 20);

        self::assertSame('daily_cleanup', $action->hook);
        self::assertSame(20, $action->priority);
    }

    #[Test]
    public function cronSchedulesFilterHasCorrectHookName(): void
    {
        $filter = new CronSchedulesFilter();

        self::assertSame('cron_schedules', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function getScheduleFilterHasCorrectHookName(): void
    {
        $filter = new GetScheduleFilter();

        self::assertSame('get_schedule', $filter->hook);
    }

    #[Test]
    public function preDoEventFilterHasCorrectHookName(): void
    {
        $filter = new PreDoEventFilter();

        self::assertSame('pre_do_event', $filter->hook);
    }

    #[Test]
    public function preScheduleEventFilterHasCorrectHookName(): void
    {
        $filter = new PreScheduleEventFilter();

        self::assertSame('pre_schedule_event', $filter->hook);
    }

    #[Test]
    public function preUnscheduleEventFilterHasCorrectHookName(): void
    {
        $filter = new PreUnscheduleEventFilter();

        self::assertSame('pre_unschedule_event', $filter->hook);
    }

    #[Test]
    public function scheduleEventFilterHasCorrectHookName(): void
    {
        $filter = new ScheduleEventFilter();

        self::assertSame('schedule_event', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new WpCronAction());
        self::assertInstanceOf(Action::class, new ScheduledEventAction(event: 'test_event'));
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new CronSchedulesFilter());
        self::assertInstanceOf(Filter::class, new GetScheduleFilter());
        self::assertInstanceOf(Filter::class, new PreDoEventFilter());
        self::assertInstanceOf(Filter::class, new PreScheduleEventFilter());
        self::assertInstanceOf(Filter::class, new PreUnscheduleEventFilter());
        self::assertInstanceOf(Filter::class, new ScheduleEventFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[WpCronAction]
            public function onWpCron(): void {}

            #[CronSchedulesFilter(priority: 5)]
            public function onCronSchedules(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onWpCron');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('wp_cron', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onCronSchedules');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('cron_schedules', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
