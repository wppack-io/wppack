<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Routing\Attribute\Action\ParseRequestAction;
use WpPack\Component\Routing\Attribute\Action\TemplateRedirectAction;
use WpPack\Component\Routing\Attribute\Filter\PageRewriteRulesFilter;
use WpPack\Component\Routing\Attribute\Filter\PostRewriteRulesFilter;
use WpPack\Component\Routing\Attribute\Filter\QueryVarsFilter;
use WpPack\Component\Routing\Attribute\Filter\RequestFilter;
use WpPack\Component\Routing\Attribute\Filter\RewriteRulesArrayFilter;
use WpPack\Component\Routing\Attribute\Filter\RootRewriteRulesFilter;
use WpPack\Component\Routing\Attribute\Filter\TemplateIncludeFilter;

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
