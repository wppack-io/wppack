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

namespace WpPack\Component\Hook\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Action\InitAction;
use WpPack\Component\Hook\Attribute\Condition\ConditionInterface;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\HookDiscovery;
use WpPack\Component\Hook\HookRegistry;
use WpPack\Component\Hook\HookType;

final class HookDiscoveryTest extends TestCase
{
    #[Test]
    public function discoversActionAttributes(): void
    {
        $registry = new HookRegistry();
        $discovery = new HookDiscovery($registry);

        $subscriber = new class {
            #[Action('init')]
            public function onInit(): void {}
        };

        $discovery->register($subscriber);

        $actions = $registry->getActions();
        self::assertCount(1, $actions);
        self::assertSame('init', $actions[0]->hook->hook);
    }

    #[Test]
    public function discoversFilterAttributes(): void
    {
        $registry = new HookRegistry();
        $discovery = new HookDiscovery($registry);

        $subscriber = new class {
            #[Filter('the_content')]
            public function filterContent(string $content): string
            {
                return $content;
            }
        };

        $discovery->register($subscriber);

        $filters = $registry->getFilters();
        self::assertCount(1, $filters);
        self::assertSame('the_content', $filters[0]->hook->hook);
    }

    #[Test]
    public function discoversNamedHookAttributes(): void
    {
        $registry = new HookRegistry();
        $discovery = new HookDiscovery($registry);

        $subscriber = new class {
            #[InitAction(priority: 5)]
            public function onInit(): void {}
        };

        $discovery->register($subscriber);

        $actions = $registry->getActions();
        self::assertCount(1, $actions);
        self::assertSame('init', $actions[0]->hook->hook);
        self::assertSame(5, $actions[0]->hook->priority);
    }

    #[Test]
    public function discoversMultipleHooksOnSameMethod(): void
    {
        $registry = new HookRegistry();
        $discovery = new HookDiscovery($registry);

        $subscriber = new class {
            #[Action('save_post')]
            #[Action('delete_post')]
            public function clearCache(int $postId): void {}
        };

        $discovery->register($subscriber);

        $actions = $registry->getActions();
        self::assertCount(2, $actions);
        self::assertSame('save_post', $actions[0]->hook->hook);
        self::assertSame('delete_post', $actions[1]->hook->hook);
    }

    #[Test]
    public function ignoresMethodsWithoutHookAttributes(): void
    {
        $registry = new HookRegistry();
        $discovery = new HookDiscovery($registry);

        $subscriber = new class {
            public function regularMethod(): void {}

            #[Action('init')]
            public function onInit(): void {}
        };

        $discovery->register($subscriber);

        self::assertCount(1, $registry->all());
    }

    #[Test]
    public function discoversConditionAttributes(): void
    {
        $registry = new HookRegistry();
        $discovery = new HookDiscovery($registry);

        $subscriber = new class {
            #[Action('init')]
            #[AlwaysTrueCondition]
            public function onInit(): void {}
        };

        $discovery->register($subscriber);

        $hooks = $registry->all();
        self::assertCount(1, $hooks);

        // Invoke the registered hook to verify condition is applied
        ($hooks[0])();
    }

    #[Test]
    public function detectsAcceptedArgs(): void
    {
        $registry = new HookRegistry();
        $discovery = new HookDiscovery($registry);

        $subscriber = new class {
            #[Filter('the_content')]
            public function filterContent(string $content): string
            {
                return $content;
            }

            #[Action('save_post')]
            public function onSave(int $postId, object $post, bool $update): void {}
        };

        $discovery->register($subscriber);

        $hooks = $registry->all();
        self::assertSame(1, $hooks[0]->acceptedArgs);
        self::assertSame(3, $hooks[1]->acceptedArgs);
    }

    #[Test]
    public function registerWithNoHookAttributesAddsNothing(): void
    {
        $registry = new HookRegistry();
        $discovery = new HookDiscovery($registry);

        $subscriber = new class {
            public function regularMethod(): void {}

            public function anotherMethod(string $value): string
            {
                return $value;
            }
        };

        $discovery->register($subscriber);

        self::assertCount(0, $registry->all());
    }

    #[Test]
    public function callbackIsBindToSubscriberInstance(): void
    {
        $registry = new HookRegistry();
        $discovery = new HookDiscovery($registry);

        $subscriber = new class {
            public bool $called = false;

            #[Action('init')]
            public function onInit(): void
            {
                $this->called = true;
            }
        };

        $discovery->register($subscriber);

        ($registry->all()[0])();

        self::assertTrue($subscriber->called);
    }
}

#[\Attribute(\Attribute::TARGET_METHOD)]
final class AlwaysTrueCondition implements ConditionInterface
{
    public function isSatisfied(): bool
    {
        return true;
    }
}
