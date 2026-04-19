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

namespace WPPack\Component\Hook\Tests\Attribute\Rest;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Hook\Attribute\Filter;
use WPPack\Component\Hook\Hook;
use WPPack\Component\Hook\HookType;
use WPPack\Component\Hook\Attribute\Rest\Action\RestApiInitAction;
use WPPack\Component\Hook\Attribute\Rest\Filter\RestAuthenticationErrorsFilter;
use WPPack\Component\Hook\Attribute\Rest\Filter\RestPreDispatchFilter;
use WPPack\Component\Hook\Attribute\Rest\Filter\RestPreServeRequestFilter;
use WPPack\Component\Hook\Attribute\Rest\Filter\RestPreparePostFilter;
use WPPack\Component\Hook\Attribute\Rest\Filter\RestRequestAfterCallbacksFilter;

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

    #[Test]
    public function restAuthenticationErrorsFilterAcceptsCustomPriority(): void
    {
        $filter = new RestAuthenticationErrorsFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function restPreDispatchFilterAcceptsCustomPriority(): void
    {
        $filter = new RestPreDispatchFilter(priority: 20);

        self::assertSame(20, $filter->priority);
    }

    #[Test]
    public function restPreServeRequestFilterAcceptsCustomPriority(): void
    {
        $filter = new RestPreServeRequestFilter(priority: 15);

        self::assertSame(15, $filter->priority);
    }

    #[Test]
    public function restPreparePostFilterAcceptsCustomPriority(): void
    {
        $filter = new RestPreparePostFilter(priority: 99);

        self::assertSame(99, $filter->priority);
    }

    #[Test]
    public function restRequestAfterCallbacksFilterAcceptsCustomPriority(): void
    {
        $filter = new RestRequestAfterCallbacksFilter(priority: 1);

        self::assertSame(1, $filter->priority);
    }
}
