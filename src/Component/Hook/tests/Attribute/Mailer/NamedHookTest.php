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

namespace WPPack\Component\Hook\Tests\Attribute\Mailer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Hook\Attribute\Filter;
use WPPack\Component\Hook\Attribute\Mailer\Action\PhpMailerInitAction;
use WPPack\Component\Hook\Attribute\Mailer\Action\WpMailFailedAction;
use WPPack\Component\Hook\Attribute\Mailer\Action\WpMailSucceededAction;
use WPPack\Component\Hook\Attribute\Mailer\Filter\PreWpMailFilter;
use WPPack\Component\Hook\Attribute\Mailer\Filter\WpMailCharsetFilter;
use WPPack\Component\Hook\Attribute\Mailer\Filter\WpMailContentTypeFilter;
use WPPack\Component\Hook\Attribute\Mailer\Filter\WpMailFilter;
use WPPack\Component\Hook\Attribute\Mailer\Filter\WpMailFromFilter;
use WPPack\Component\Hook\Attribute\Mailer\Filter\WpMailFromNameFilter;
use WPPack\Component\Hook\Hook;
use WPPack\Component\Hook\HookType;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function phpMailerInitActionHasCorrectHookName(): void
    {
        $action = new PhpMailerInitAction();

        self::assertSame('phpmailer_init', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function phpMailerInitActionAcceptsCustomPriority(): void
    {
        $action = new PhpMailerInitAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function wpMailFailedActionHasCorrectHookName(): void
    {
        $action = new WpMailFailedAction();

        self::assertSame('wp_mail_failed', $action->hook);
    }

    #[Test]
    public function wpMailSucceededActionHasCorrectHookName(): void
    {
        $action = new WpMailSucceededAction();

        self::assertSame('wp_mail_succeeded', $action->hook);
    }

    #[Test]
    public function preWpMailFilterHasCorrectHookName(): void
    {
        $filter = new PreWpMailFilter();

        self::assertSame('pre_wp_mail', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function wpMailCharsetFilterHasCorrectHookName(): void
    {
        $filter = new WpMailCharsetFilter();

        self::assertSame('wp_mail_charset', $filter->hook);
    }

    #[Test]
    public function wpMailContentTypeFilterHasCorrectHookName(): void
    {
        $filter = new WpMailContentTypeFilter();

        self::assertSame('wp_mail_content_type', $filter->hook);
    }

    #[Test]
    public function wpMailFilterHasCorrectHookName(): void
    {
        $filter = new WpMailFilter();

        self::assertSame('wp_mail', $filter->hook);
    }

    #[Test]
    public function wpMailFromFilterHasCorrectHookName(): void
    {
        $filter = new WpMailFromFilter();

        self::assertSame('wp_mail_from', $filter->hook);
    }

    #[Test]
    public function wpMailFromNameFilterHasCorrectHookName(): void
    {
        $filter = new WpMailFromNameFilter();

        self::assertSame('wp_mail_from_name', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new PhpMailerInitAction());
        self::assertInstanceOf(Action::class, new WpMailFailedAction());
        self::assertInstanceOf(Action::class, new WpMailSucceededAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new PreWpMailFilter());
        self::assertInstanceOf(Filter::class, new WpMailCharsetFilter());
        self::assertInstanceOf(Filter::class, new WpMailContentTypeFilter());
        self::assertInstanceOf(Filter::class, new WpMailFilter());
        self::assertInstanceOf(Filter::class, new WpMailFromFilter());
        self::assertInstanceOf(Filter::class, new WpMailFromNameFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[PhpMailerInitAction]
            public function onMailerInit(): void {}

            #[WpMailFromFilter(priority: 5)]
            public function onMailFrom(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onMailerInit');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('phpmailer_init', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onMailFrom');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('wp_mail_from', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
