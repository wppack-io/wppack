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

namespace WPPack\Component\Scim\Tests\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\EventDispatcher\WordPressEvent;
use WPPack\Component\Scim\Authentication\ScimUserStatusChecker;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\User\UserRepositoryInterface;

#[CoversClass(ScimUserStatusChecker::class)]
final class ScimUserStatusCheckerTest extends TestCase
{
    private function user(): \WP_User
    {
        $userId = (int) \wp_insert_user([
            'user_login' => 'scim_status_' . \uniqid(),
            'user_email' => 'scim_status_' . \uniqid() . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]);

        return new \WP_User($userId);
    }

    private function event(mixed $filterValue): WordPressEvent
    {
        return new WordPressEvent('wp_authenticate_user', [$filterValue]);
    }

    #[Test]
    public function deactivatedUserReplacedWithWpError(): void
    {
        $user = $this->user();
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('getMeta')
            ->with($user->ID, ScimConstants::META_ACTIVE, true)
            ->willReturn('0');

        $event = $this->event($user);
        (new ScimUserStatusChecker($repo))($event);

        self::assertInstanceOf(\WP_Error::class, $event->filterValue);
        self::assertSame('scim_user_deactivated', $event->filterValue->get_error_code());
    }

    #[Test]
    public function activeUserLeftAlone(): void
    {
        $user = $this->user();
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('getMeta')->willReturn('1');

        $event = $this->event($user);
        (new ScimUserStatusChecker($repo))($event);

        self::assertSame($user, $event->filterValue);
    }

    #[Test]
    public function userWithoutActiveMetaIsLeftAlone(): void
    {
        $user = $this->user();
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('getMeta')->willReturn('');

        $event = $this->event($user);
        (new ScimUserStatusChecker($repo))($event);

        self::assertSame($user, $event->filterValue);
    }

    #[Test]
    public function nonUserEventIsIgnored(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects(self::never())->method('getMeta');

        $wpError = new \WP_Error('some_error', 'message');
        $event = $this->event($wpError);
        (new ScimUserStatusChecker($repo))($event);

        self::assertSame($wpError, $event->filterValue);
    }
}
