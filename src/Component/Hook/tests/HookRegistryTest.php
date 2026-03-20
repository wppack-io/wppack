<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Exception\LogicException;
use WpPack\Component\Hook\HookRegistry;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\RegisteredHook;

final class HookRegistryTest extends TestCase
{
    #[Test]
    public function addRegistersHook(): void
    {
        $registry = new HookRegistry();
        $hook = new RegisteredHook(
            new Action('init'),
            static fn() => null,
            0,
        );

        $result = $registry->add($hook);

        self::assertSame($registry, $result);
        self::assertCount(1, $registry->all());
    }

    #[Test]
    public function addActionRegistersAction(): void
    {
        $registry = new HookRegistry();

        $registry->addAction('init', static function (): void {});

        $actions = $registry->getActions();
        self::assertCount(1, $actions);
        self::assertSame('init', $actions[0]->hook->hook);
        self::assertSame(HookType::Action, $actions[0]->hook->type);
    }

    #[Test]
    public function addFilterRegistersFilter(): void
    {
        $registry = new HookRegistry();

        $registry->addFilter('the_content', static fn(string $content): string => $content);

        $filters = $registry->getFilters();
        self::assertCount(1, $filters);
        self::assertSame('the_content', $filters[0]->hook->hook);
        self::assertSame(HookType::Filter, $filters[0]->hook->type);
    }

    #[Test]
    public function addActionDetectsAcceptedArgs(): void
    {
        $registry = new HookRegistry();

        $registry->addAction('save_post', static function (int $postId, object $post, bool $update): void {});

        $actions = $registry->getActions();
        self::assertSame(3, $actions[0]->acceptedArgs);
    }

    #[Test]
    public function addActionSupportsCustomPriority(): void
    {
        $registry = new HookRegistry();

        $registry->addAction('init', static function (): void {}, 20);

        $actions = $registry->getActions();
        self::assertSame(20, $actions[0]->hook->priority);
    }

    #[Test]
    public function getActionsReturnsOnlyActions(): void
    {
        $registry = new HookRegistry();
        $registry->addAction('init', static function (): void {});
        $registry->addFilter('the_content', static fn(string $c): string => $c);

        self::assertCount(1, $registry->getActions());
        self::assertCount(1, $registry->getFilters());
        self::assertCount(2, $registry->all());
    }

    #[Test]
    public function throwsWhenAddingAfterRegister(): void
    {
        $registry = new HookRegistry();
        $registry->register();

        $this->expectException(LogicException::class);
        $registry->addAction('init', static function (): void {});
    }

    #[Test]
    public function throwsWhenAddingRegisteredHookAfterRegister(): void
    {
        $registry = new HookRegistry();
        $registry->register();

        $this->expectException(LogicException::class);
        $registry->add(new RegisteredHook(
            new Action('init'),
            static fn() => null,
            0,
        ));
    }

    #[Test]
    public function registerIsIdempotent(): void
    {
        $registry = new HookRegistry();
        $registry->addAction('init', static function (): void {});

        $registry->register();
        $registry->register(); // should not throw

        self::assertCount(1, $registry->all());
    }

    #[Test]
    public function fluentInterface(): void
    {
        $registry = new HookRegistry();

        $result = $registry
            ->addAction('init', static function (): void {})
            ->addFilter('the_content', static fn(string $c): string => $c);

        self::assertSame($registry, $result);
        self::assertCount(2, $registry->all());
    }
}
