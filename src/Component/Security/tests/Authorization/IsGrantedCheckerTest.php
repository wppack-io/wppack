<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Authorization;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Attribute\IsGranted;
use WpPack\Component\Security\Authorization\IsGrantedChecker;
use WpPack\Component\Security\Exception\AccessDeniedException;
use WpPack\Component\Security\Tests\SecurityTestTrait;

final class IsGrantedCheckerTest extends TestCase
{
    use SecurityTestTrait;

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
    public function checkPassesWithSecurity(): void
    {
        $security = $this->createSecurity(granted: true);
        $checker = new IsGrantedChecker($security);

        $grants = [new IsGranted('edit_posts')];

        $checker->check($grants);

        self::assertTrue(true);
    }

    #[Test]
    public function checkThrowsWithSecurityWhenDenied(): void
    {
        $security = $this->createSecurity(granted: false);
        $checker = new IsGrantedChecker($security);

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

        $checker->check($grants);

        self::assertTrue(true);
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

        $checker->check([]);

        self::assertTrue(true);
    }

    #[Test]
    public function checkAllGrantsMustPass(): void
    {
        $security = $this->createSecurity(granted: false);
        $checker = new IsGrantedChecker($security);

        $grants = [
            new IsGranted('edit_posts'),
            new IsGranted('manage_options'),
        ];

        $this->expectException(AccessDeniedException::class);

        $checker->check($grants);
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
}
