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

namespace WPPack\Component\Role\Tests\Authorization;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Role\Attribute\IsGranted;
use WPPack\Component\Role\Authorization\AuthorizationCheckerInterface;
use WPPack\Component\Role\Authorization\IsGrantedChecker;
use WPPack\Component\Role\Exception\AccessDeniedException;

final class IsGrantedCheckerTest extends TestCase
{
    #[Test]
    public function resolveCollectsFromClassOnly(): void
    {
        $class = new #[IsGranted('edit_posts')] class {};

        $reflection = new \ReflectionClass($class);
        $grants = IsGrantedChecker::resolve($reflection);

        self::assertCount(1, $grants);
        self::assertSame('edit_posts', $grants[0]->attribute);
    }

    #[Test]
    public function resolveCollectsFromMethodOnly(): void
    {
        $class = new class {
            #[IsGranted('manage_options')]
            public function handle(): void {}
        };

        $reflection = new \ReflectionClass($class);
        $method = $reflection->getMethod('handle');
        $grants = IsGrantedChecker::resolve($reflection, $method);

        self::assertCount(1, $grants);
        self::assertSame('manage_options', $grants[0]->attribute);
    }

    #[Test]
    public function resolveCollectsFromClassAndMethod(): void
    {
        $class = new #[IsGranted('edit_posts')] class {
            #[IsGranted('manage_options')]
            public function handle(): void {}
        };

        $reflection = new \ReflectionClass($class);
        $method = $reflection->getMethod('handle');
        $grants = IsGrantedChecker::resolve($reflection, $method);

        self::assertCount(2, $grants);
        self::assertSame('edit_posts', $grants[0]->attribute);
        self::assertSame('manage_options', $grants[1]->attribute);
    }

    #[Test]
    public function resolveReturnsEmptyWhenNoAttributes(): void
    {
        $class = new class {
            public function handle(): void {}
        };

        $reflection = new \ReflectionClass($class);
        $grants = IsGrantedChecker::resolve($reflection);

        self::assertSame([], $grants);
    }

    #[Test]
    public function resolveCollectsMultipleFromSameTarget(): void
    {
        $class = new #[IsGranted('edit_posts')] #[IsGranted('ROLE_EDITOR')] class {};

        $reflection = new \ReflectionClass($class);
        $grants = IsGrantedChecker::resolve($reflection);

        self::assertCount(2, $grants);
    }

    #[Test]
    public function checkPassesWithAuthorizationChecker(): void
    {
        $checker = new IsGrantedChecker($this->createAuthorizationChecker(true));

        $grants = [new IsGranted('edit_posts')];

        $this->expectNotToPerformAssertions();
        $checker->check($grants);
    }

    #[Test]
    public function checkThrowsWithAuthorizationCheckerWhenDenied(): void
    {
        $checker = new IsGrantedChecker($this->createAuthorizationChecker(false));

        $grants = [new IsGranted('edit_posts', message: 'No access.')];

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('No access.');

        $checker->check($grants);
    }

    #[Test]
    public function checkFallsBackToCurrentUserCan(): void
    {
        $checker = new IsGrantedChecker();

        wp_set_current_user(1);
        $grants = [new IsGranted('manage_options')];

        $this->expectNotToPerformAssertions();
        $checker->check($grants);
    }

    #[Test]
    public function checkFallbackThrowsWhenDenied(): void
    {
        $checker = new IsGrantedChecker();

        wp_set_current_user(0);
        $grants = [new IsGranted('manage_options')];

        $this->expectException(AccessDeniedException::class);

        $checker->check($grants);
    }

    #[Test]
    public function checkPassesWithEmptyGrants(): void
    {
        $checker = new IsGrantedChecker();

        $this->expectNotToPerformAssertions();
        $checker->check([]);
    }

    #[Test]
    public function checkAllGrantsMustPass(): void
    {
        $checker = new IsGrantedChecker($this->createAuthorizationChecker(false));

        $grants = [
            new IsGranted('edit_posts'),
            new IsGranted('manage_options'),
        ];

        $this->expectException(AccessDeniedException::class);

        $checker->check($grants);
    }

    #[Test]
    public function checkPassesStatusCodeToException(): void
    {
        $checker = new IsGrantedChecker($this->createAuthorizationChecker(false));

        $grants = [new IsGranted('edit_posts', statusCode: 404)];

        try {
            $checker->check($grants);
            self::fail('Expected AccessDeniedException');
        } catch (AccessDeniedException $e) {
            self::assertSame(404, $e->getCode());
        }
    }

    #[Test]
    public function checkDefaultStatusCodeIs403(): void
    {
        $checker = new IsGrantedChecker($this->createAuthorizationChecker(false));

        $grants = [new IsGranted('edit_posts')];

        try {
            $checker->check($grants);
            self::fail('Expected AccessDeniedException');
        } catch (AccessDeniedException $e) {
            self::assertSame(403, $e->getCode());
        }
    }

    #[Test]
    public function isAllGrantedReturnsTrueWhenAllGranted(): void
    {
        $checker = new IsGrantedChecker($this->createAuthorizationChecker(true));

        $grants = [new IsGranted('edit_posts'), new IsGranted('manage_options')];

        self::assertTrue($checker->isAllGranted($grants));
    }

    #[Test]
    public function isAllGrantedReturnsFalseWhenDenied(): void
    {
        $checker = new IsGrantedChecker($this->createAuthorizationChecker(false));

        $grants = [new IsGranted('edit_posts')];

        self::assertFalse($checker->isAllGranted($grants));
    }

    #[Test]
    public function isAllGrantedReturnsTrueForEmptyGrants(): void
    {
        $checker = new IsGrantedChecker();

        self::assertTrue($checker->isAllGranted([]));
    }

    #[Test]
    public function isAllGrantedFallsBackToCurrentUserCan(): void
    {
        $checker = new IsGrantedChecker();

        wp_set_current_user(1);
        $grants = [new IsGranted('manage_options')];

        self::assertTrue($checker->isAllGranted($grants));
    }

    #[Test]
    public function isAllGrantedFallbackReturnsFalseWhenDenied(): void
    {
        $checker = new IsGrantedChecker();

        wp_set_current_user(0);
        $grants = [new IsGranted('manage_options')];

        self::assertFalse($checker->isAllGranted($grants));
    }

    #[Test]
    public function extractCapabilityReturnsFirstAttribute(): void
    {
        $class = new #[IsGranted('edit_posts')] class {};

        $reflection = new \ReflectionClass($class);
        $capability = IsGrantedChecker::extractCapability($reflection);

        self::assertSame('edit_posts', $capability);
    }

    #[Test]
    public function extractCapabilityReturnsDefaultWhenMissing(): void
    {
        $class = new class {};

        $reflection = new \ReflectionClass($class);
        $capability = IsGrantedChecker::extractCapability($reflection);

        self::assertSame('manage_options', $capability);
    }

    #[Test]
    public function extractCapabilityReturnsFirstOfMultiple(): void
    {
        $class = new #[IsGranted('edit_posts')] #[IsGranted('manage_options')] class {};

        $reflection = new \ReflectionClass($class);
        $capability = IsGrantedChecker::extractCapability($reflection);

        self::assertSame('edit_posts', $capability);
    }

    private function createAuthorizationChecker(bool $granted): AuthorizationCheckerInterface
    {
        return new class ($granted) implements AuthorizationCheckerInterface {
            public function __construct(private readonly bool $granted) {}

            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return $this->granted;
            }
        };
    }
}
