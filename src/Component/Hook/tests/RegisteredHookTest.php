<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\RegisteredHook;

#[CoversClass(RegisteredHook::class)]
final class RegisteredHookTest extends TestCase
{
    #[Test]
    public function invokeWithoutConditionsExecutesCallback(): void
    {
        $hook = new RegisteredHook(
            hook: new Action('init'),
            callback: fn () => 'executed',
            acceptedArgs: 0,
        );

        self::assertSame('executed', $hook());
    }

    #[Test]
    public function invokeWithSatisfiedConditionExecutesCallback(): void
    {
        $hook = new RegisteredHook(
            hook: new Action('init'),
            callback: fn () => 'executed',
            acceptedArgs: 0,
            conditions: [new AlwaysTrueCondition()],
        );

        self::assertSame('executed', $hook());
    }

    #[Test]
    public function invokeWithUnsatisfiedConditionOnActionReturnsNull(): void
    {
        $hook = new RegisteredHook(
            hook: new Action('init'),
            callback: fn () => 'should not execute',
            acceptedArgs: 0,
            conditions: [new AlwaysFalseCondition()],
        );

        self::assertNull($hook());
    }

    #[Test]
    public function invokeWithUnsatisfiedConditionOnFilterReturnsFirstArg(): void
    {
        $hook = new RegisteredHook(
            hook: new Filter('the_content'),
            callback: fn (string $content) => 'modified',
            acceptedArgs: 1,
            conditions: [new AlwaysFalseCondition()],
        );

        self::assertSame('original', $hook('original'));
    }

    #[Test]
    public function invokeWithUnsatisfiedConditionOnFilterWithNoArgsReturnsNull(): void
    {
        $hook = new RegisteredHook(
            hook: new Filter('the_content'),
            callback: fn () => 'modified',
            acceptedArgs: 0,
            conditions: [new AlwaysFalseCondition()],
        );

        self::assertNull($hook());
    }

    #[Test]
    public function invokeWithMultipleConditionsStopsAtFirstFailure(): void
    {
        $hook = new RegisteredHook(
            hook: new Action('init'),
            callback: fn () => 'should not execute',
            acceptedArgs: 0,
            conditions: [new AlwaysTrueCondition(), new AlwaysFalseCondition()],
        );

        self::assertNull($hook());
    }

    #[Test]
    public function invokePassesAllArgumentsToCallback(): void
    {
        $hook = new RegisteredHook(
            hook: new Action('save_post'),
            callback: fn (int $postId, object $post, bool $update) => [$postId, $post, $update],
            acceptedArgs: 3,
        );

        $post = (object) ['ID' => 1];
        $result = $hook(1, $post, true);

        self::assertSame([1, $post, true], $result);
    }
}

#[\Attribute(\Attribute::TARGET_METHOD)]
final class AlwaysFalseCondition implements \WpPack\Component\Hook\Attribute\Condition\ConditionInterface
{
    public function isSatisfied(): bool
    {
        return false;
    }
}
