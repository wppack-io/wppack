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

namespace WpPack\Component\Security\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Exception\AccessDeniedException;
use WpPack\Component\Security\SecurityAwareTrait;

final class SecurityAwareTraitTest extends TestCase
{
    use SecurityTestTrait;

    #[Test]
    public function getUserReturnUserWhenSecurityAvailable(): void
    {
        $user = new \WP_User();
        $user->ID = 42;

        $security = $this->createSecurity(user: $user);
        $controller = $this->createTraitUser();
        $controller->setSecurity($security);

        self::assertSame($user, $controller->callGetUser());
    }

    #[Test]
    public function getUserThrowsWhenSecurityNotAvailable(): void
    {
        $controller = $this->createTraitUser();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Security is not available');

        $controller->callGetUser();
    }

    #[Test]
    public function isGrantedDelegatesToSecurity(): void
    {
        $security = $this->createSecurity(granted: true);
        $controller = $this->createTraitUser();
        $controller->setSecurity($security);

        self::assertTrue($controller->callIsGranted('edit_posts'));
    }

    #[Test]
    public function isGrantedThrowsWhenSecurityNotAvailable(): void
    {
        $controller = $this->createTraitUser();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Security is not available');

        $controller->callIsGranted('edit_posts');
    }

    #[Test]
    public function denyAccessUnlessGrantedPassesWhenGranted(): void
    {
        $security = $this->createSecurity(granted: true);
        $controller = $this->createTraitUser();
        $controller->setSecurity($security);

        $controller->callDenyAccessUnlessGranted('edit_posts');

        self::assertTrue(true);
    }

    #[Test]
    public function denyAccessUnlessGrantedThrowsWhenDenied(): void
    {
        $security = $this->createSecurity(granted: false);
        $controller = $this->createTraitUser();
        $controller->setSecurity($security);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied.');

        $controller->callDenyAccessUnlessGranted('edit_posts');
    }

    #[Test]
    public function denyAccessUnlessGrantedThrowsWhenSecurityNotAvailable(): void
    {
        $controller = $this->createTraitUser();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Security is not available');

        $controller->callDenyAccessUnlessGranted('edit_posts');
    }

    private function createTraitUser(): object
    {
        return new class {
            use SecurityAwareTrait;

            public function callGetUser(): ?\WP_User
            {
                return $this->getUser();
            }

            public function callIsGranted(string $attribute, mixed $subject = null): bool
            {
                return $this->isGranted($attribute, $subject);
            }

            public function callDenyAccessUnlessGranted(string $attribute, mixed $subject = null, string $message = 'Access Denied.'): void
            {
                $this->denyAccessUnlessGranted($attribute, $subject, $message);
            }
        };
    }
}
