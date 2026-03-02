<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Rest\Attribute\Action\RestApiInitAction;
use WpPack\Component\Rest\Attribute\Filter\DetermineCurrentUserFilter;
use WpPack\Component\Rest\Attribute\Filter\RestAuthenticationErrorsFilter;
use WpPack\Component\Rest\Attribute\Filter\RestPreDispatchFilter;
use WpPack\Component\Rest\Attribute\Filter\RestPreServeRequestFilter;
use WpPack\Component\Rest\Attribute\Filter\RestPreparePostFilter;
use WpPack\Component\Rest\Attribute\Filter\RestRequestAfterCallbacksFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function restApiInitActionHasCorrectHookName(): void
    {
        $action = new RestApiInitAction();

        self::assertSame('rest_api_init', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function restApiInitActionAcceptsCustomPriority(): void
    {
        $action = new RestApiInitAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function determineCurrentUserFilterHasCorrectHookName(): void
    {
        $filter = new DetermineCurrentUserFilter();

        self::assertSame('determine_current_user', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function restAuthenticationErrorsFilterHasCorrectHookName(): void
    {
        $filter = new RestAuthenticationErrorsFilter();

        self::assertSame('rest_authentication_errors', $filter->hook);
    }

    #[Test]
    public function restPreDispatchFilterHasCorrectHookName(): void
    {
        $filter = new RestPreDispatchFilter();

        self::assertSame('rest_pre_dispatch', $filter->hook);
    }

    #[Test]
    public function restPreServeRequestFilterHasCorrectHookName(): void
    {
        $filter = new RestPreServeRequestFilter();

        self::assertSame('rest_pre_serve_request', $filter->hook);
    }

    #[Test]
    public function restPreparePostFilterHasCorrectHookName(): void
    {
        $filter = new RestPreparePostFilter();

        self::assertSame('rest_prepare_post', $filter->hook);
    }

    #[Test]
    public function restRequestAfterCallbacksFilterHasCorrectHookName(): void
    {
        $filter = new RestRequestAfterCallbacksFilter();

        self::assertSame('rest_request_after_callbacks', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new RestApiInitAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new DetermineCurrentUserFilter());
        self::assertInstanceOf(Filter::class, new RestAuthenticationErrorsFilter());
        self::assertInstanceOf(Filter::class, new RestPreDispatchFilter());
        self::assertInstanceOf(Filter::class, new RestPreServeRequestFilter());
        self::assertInstanceOf(Filter::class, new RestPreparePostFilter());
        self::assertInstanceOf(Filter::class, new RestRequestAfterCallbacksFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[RestApiInitAction]
            public function onRestApiInit(): void {}

            #[RestAuthenticationErrorsFilter(priority: 5)]
            public function onRestAuthErrors(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onRestApiInit');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('rest_api_init', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onRestAuthErrors');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('rest_authentication_errors', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
