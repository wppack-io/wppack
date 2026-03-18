<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Tests\Attribute\Routing;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\Routing\Action\ParseRequestAction;
use WpPack\Component\Hook\Attribute\Routing\Action\TemplateRedirectAction;
use WpPack\Component\Hook\Attribute\Routing\Filter\PageRewriteRulesFilter;
use WpPack\Component\Hook\Attribute\Routing\Filter\PostRewriteRulesFilter;
use WpPack\Component\Hook\Attribute\Routing\Filter\QueryVarsFilter;
use WpPack\Component\Hook\Attribute\Routing\Filter\RequestFilter;
use WpPack\Component\Hook\Attribute\Routing\Filter\RewriteRulesArrayFilter;
use WpPack\Component\Hook\Attribute\Routing\Filter\RootRewriteRulesFilter;
use WpPack\Component\Hook\Attribute\Routing\Filter\TemplateIncludeFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function parseRequestActionHasCorrectHookName(): void
    {
        $action = new ParseRequestAction();

        self::assertSame('parse_request', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function templateRedirectActionHasCorrectHookName(): void
    {
        $action = new TemplateRedirectAction();

        self::assertSame('template_redirect', $action->hook);
    }

    #[Test]
    public function parseRequestActionAcceptsCustomPriority(): void
    {
        $action = new ParseRequestAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function pageRewriteRulesFilterHasCorrectHookName(): void
    {
        $filter = new PageRewriteRulesFilter();

        self::assertSame('page_rewrite_rules', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function postRewriteRulesFilterHasCorrectHookName(): void
    {
        $filter = new PostRewriteRulesFilter();

        self::assertSame('post_rewrite_rules', $filter->hook);
    }

    #[Test]
    public function queryVarsFilterHasCorrectHookName(): void
    {
        $filter = new QueryVarsFilter();

        self::assertSame('query_vars', $filter->hook);
    }

    #[Test]
    public function requestFilterHasCorrectHookName(): void
    {
        $filter = new RequestFilter();

        self::assertSame('request', $filter->hook);
    }

    #[Test]
    public function rewriteRulesArrayFilterHasCorrectHookName(): void
    {
        $filter = new RewriteRulesArrayFilter();

        self::assertSame('rewrite_rules_array', $filter->hook);
    }

    #[Test]
    public function rootRewriteRulesFilterHasCorrectHookName(): void
    {
        $filter = new RootRewriteRulesFilter();

        self::assertSame('root_rewrite_rules', $filter->hook);
    }

    #[Test]
    public function templateIncludeFilterHasCorrectHookName(): void
    {
        $filter = new TemplateIncludeFilter();

        self::assertSame('template_include', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new ParseRequestAction());
        self::assertInstanceOf(Action::class, new TemplateRedirectAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new PageRewriteRulesFilter());
        self::assertInstanceOf(Filter::class, new PostRewriteRulesFilter());
        self::assertInstanceOf(Filter::class, new QueryVarsFilter());
        self::assertInstanceOf(Filter::class, new RequestFilter());
        self::assertInstanceOf(Filter::class, new RewriteRulesArrayFilter());
        self::assertInstanceOf(Filter::class, new RootRewriteRulesFilter());
        self::assertInstanceOf(Filter::class, new TemplateIncludeFilter());
    }

    #[Test]
    public function templateRedirectActionAcceptsCustomPriority(): void
    {
        $action = new TemplateRedirectAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function pageRewriteRulesFilterAcceptsCustomPriority(): void
    {
        $filter = new PageRewriteRulesFilter(priority: 20);

        self::assertSame(20, $filter->priority);
    }

    #[Test]
    public function postRewriteRulesFilterAcceptsCustomPriority(): void
    {
        $filter = new PostRewriteRulesFilter(priority: 15);

        self::assertSame(15, $filter->priority);
    }

    #[Test]
    public function queryVarsFilterAcceptsCustomPriority(): void
    {
        $filter = new QueryVarsFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function requestFilterAcceptsCustomPriority(): void
    {
        $filter = new RequestFilter(priority: 99);

        self::assertSame(99, $filter->priority);
    }

    #[Test]
    public function rewriteRulesArrayFilterAcceptsCustomPriority(): void
    {
        $filter = new RewriteRulesArrayFilter(priority: 1);

        self::assertSame(1, $filter->priority);
    }

    #[Test]
    public function rootRewriteRulesFilterAcceptsCustomPriority(): void
    {
        $filter = new RootRewriteRulesFilter(priority: 50);

        self::assertSame(50, $filter->priority);
    }

    #[Test]
    public function templateIncludeFilterAcceptsCustomPriority(): void
    {
        $filter = new TemplateIncludeFilter(priority: 30);

        self::assertSame(30, $filter->priority);
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[ParseRequestAction]
            public function onParseRequest(): void {}

            #[TemplateIncludeFilter(priority: 5)]
            public function onTemplateInclude(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onParseRequest');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('parse_request', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onTemplateInclude');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('template_include', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
